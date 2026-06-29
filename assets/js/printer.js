/**
 * Módulo de impresión térmica para Android
 * Soporta: Web Bluetooth (ESC/POS), impresión navegador, impresora de red vía servidor
 */
const ThermalPrinter = (() => {
    let bluetoothDevice = null;
    let bluetoothCharacteristic = null;

    const PRINTER_SERVICE_UUIDS = [
        '000018f0-0000-1000-8000-00805f9b34fb',
        '49535343-fe7d-4ae0-88c5-f6b13c4f4b80',
        '0000ff00-0000-1000-8000-00805f9b34fb',
    ];

    const PRINTER_CHAR_UUIDS = [
        '00002af1-0000-1000-8000-00805f9b34fb',
        '49535343-8841-43f4-a8d4-ecbe34729bb3',
        '0000ff02-0000-1000-8000-00805f9b34fb',
    ];

    function isBluetoothSupported() {
        return 'bluetooth' in navigator;
    }

    async function connectBluetooth() {
        if (!isBluetoothSupported()) {
            throw new Error('Bluetooth no disponible en este navegador. Usa Chrome en Android.');
        }

        const device = await navigator.bluetooth.requestDevice({
            acceptAllDevices: true,
            optionalServices: PRINTER_SERVICE_UUIDS,
        });

        const server = await device.gatt.connect();
        let characteristic = null;

        for (const serviceUuid of PRINTER_SERVICE_UUIDS) {
            try {
                const service = await server.getPrimaryService(serviceUuid);
                for (const charUuid of PRINTER_CHAR_UUIDS) {
                    try {
                        characteristic = await service.getCharacteristic(charUuid);
                        break;
                    } catch (_) { /* siguiente */ }
                }
                if (characteristic) break;
            } catch (_) { /* siguiente servicio */ }
        }

        if (!characteristic) {
            const services = await server.getPrimaryServices();
            for (const service of services) {
                const chars = await service.getCharacteristics();
                const writable = chars.find(c => c.properties.write || c.properties.writeWithoutResponse);
                if (writable) {
                    characteristic = writable;
                    break;
                }
            }
        }

        if (!characteristic) {
            throw new Error('No se encontró característica de escritura en la impresora.');
        }

        bluetoothDevice = device;
        bluetoothCharacteristic = characteristic;

        device.addEventListener('gattserverdisconnected', () => {
            bluetoothDevice = null;
            bluetoothCharacteristic = null;
        });

        return device.name || 'Impresora Bluetooth';
    }

    function disconnectBluetooth() {
        if (bluetoothDevice?.gatt?.connected) {
            bluetoothDevice.gatt.disconnect();
        }
        bluetoothDevice = null;
        bluetoothCharacteristic = null;
    }

    function isConnected() {
        return !!(bluetoothDevice?.gatt?.connected && bluetoothCharacteristic);
    }

    async function sendEscPos(base64Data) {
        if (!bluetoothCharacteristic) {
            throw new Error('Impresora Bluetooth no conectada.');
        }

        const binary = atob(base64Data);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }

        const chunkSize = 512;
        for (let i = 0; i < bytes.length; i += chunkSize) {
            const chunk = bytes.slice(i, i + chunkSize);
            if (bluetoothCharacteristic.properties.writeWithoutResponse) {
                await bluetoothCharacteristic.writeValueWithoutResponse(chunk);
            } else {
                await bluetoothCharacteristic.writeValue(chunk);
            }
            await delay(50);
        }
    }

    function delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    function printBrowser(receiptText) {
        const printArea = document.getElementById('printArea');
        if (!printArea) return;

        printArea.hidden = false;
        printArea.innerHTML = `<pre class="receipt-print">${escapeHtml(receiptText)}</pre>`;

        const cleanup = () => {
            printArea.hidden = true;
            printArea.innerHTML = '';
        };

        window.onafterprint = cleanup;
        setTimeout(() => {
            window.print();
            setTimeout(cleanup, 1000);
        }, 100);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    async function printOrder(orderData, settings) {
        const response = await fetch('api/print.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order: orderData,
                cafe_name: settings.cafeName || 'Artemisa Salón de Té',
            }),
        });

        const result = await response.json();
        if (!result.success) {
            throw new Error(result.error || 'Error al generar comanda.');
        }

        const mode = settings.printerMode || 'bluetooth';

        if (mode === 'network') {
            if (result.printed_network) {
                return { success: true, method: 'network' };
            }
            throw new Error(result.print_error || 'Impresora de red no disponible.');
        }

        if (mode === 'bluetooth' && isBluetoothSupported()) {
            if (!isConnected()) {
                throw new Error('Conecta la impresora Bluetooth en Configuración.');
            }
            await sendEscPos(result.receipt_base64);
            return { success: true, method: 'bluetooth' };
        }

        printBrowser(result.receipt_text);
        return { success: true, method: 'browser' };
    }

    return {
        isBluetoothSupported,
        connectBluetooth,
        disconnectBluetooth,
        isConnected,
        printOrder,
        printBrowser,
    };
})();
