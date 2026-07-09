/**
 * Módulo de impresión térmica para Android
 * Soporta: Web Bluetooth (ESC/POS), impresión navegador, impresora de red vía servidor
 */
const ThermalPrinter = (() => {
    let bluetoothDevice = null;
    let bluetoothCharacteristic = null;

    /**
     * PT-210 58mm @ 28 columnas: lotes de hasta 3 líneas (~87 bytes).
     * Chunks BLE siguen siendo <= 20 bytes.
     */
    const BLE_CHUNK_MAX = 20;
    const BATCH_MAX_BYTES = 90;
    const BATCH_MAX_LINES = 3;
    const BATCH_PAUSE_MS = 80;
    const ITEM_SEPARATOR_EXTRA_MS = 55;
    const CHUNK_DELAY_WITH_RESPONSE_MS = 45;
    const CHUNK_DELAY_WITHOUT_RESPONSE_MS = 60;

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

    function getBleChunkSize() {
        return BLE_CHUNK_MAX;
    }

    function getUtf8CharLength(byte) {
        if ((byte & 0x80) === 0) return 1;
        if ((byte & 0xe0) === 0xc0) return 2;
        if ((byte & 0xf0) === 0xe0) return 3;
        if ((byte & 0xf8) === 0xf0) return 4;
        return 1;
    }

    function getEscPosCommandLength(bytes, pos) {
        const b0 = bytes[pos];
        if (b0 !== 0x1b && b0 !== 0x1d) return 1;
        if (pos + 2 < bytes.length) return 3;
        if (pos + 1 < bytes.length) return 2;
        return 1;
    }

    function findSafeSplitEnd(bytes, start, maxLen) {
        const limit = Math.min(start + maxLen, bytes.length);
        if (limit >= bytes.length) return bytes.length;

        let splitAt = limit;

        while (splitAt > start && (bytes[splitAt - 1] & 0xc0) === 0x80) {
            splitAt--;
        }

        let i = start;
        while (i < splitAt) {
            if (bytes[i] === 0x1b || bytes[i] === 0x1d) {
                const cmdLen = getEscPosCommandLength(bytes, i);
                if (i + cmdLen > splitAt) {
                    splitAt = i;
                    break;
                }
                i += cmdLen;
                continue;
            }
            if ((bytes[i] & 0x80) !== 0) {
                const utfLen = getUtf8CharLength(bytes[i]);
                if (i + utfLen > splitAt) {
                    splitAt = i;
                    break;
                }
                i += utfLen;
                continue;
            }
            i++;
        }

        if (splitAt <= start) {
            splitAt = limit;
            while (splitAt > start && (bytes[splitAt - 1] & 0xc0) === 0x80) {
                splitAt--;
            }
            if (splitAt <= start) splitAt = limit;
        }

        return splitAt;
    }

    function buildBleChunks(bytes, maxChunkSize) {
        const chunks = [];
        let pos = 0;
        while (pos < bytes.length) {
            const end = findSafeSplitEnd(bytes, pos, maxChunkSize);
            const next = end > pos ? end : Math.min(pos + maxChunkSize, bytes.length);
            chunks.push(bytes.slice(pos, next));
            pos = next;
        }
        return chunks;
    }

    function splitIntoLines(bytes) {
        const lines = [];
        let start = 0;
        for (let i = 0; i < bytes.length; i++) {
            if (bytes[i] === 0x0a) {
                lines.push(bytes.slice(start, i + 1));
                start = i + 1;
            }
        }
        if (start < bytes.length) {
            lines.push(bytes.slice(start));
        }
        return lines;
    }

    function concatLines(lines) {
        const total = lines.reduce((sum, line) => sum + line.length, 0);
        const out = new Uint8Array(total);
        let offset = 0;
        for (const line of lines) {
            out.set(line, offset);
            offset += line.length;
        }
        return out;
    }

    function isBlankLine(lineBytes) {
        return lineBytes.length === 0 || (lineBytes.length === 1 && lineBytes[0] === 0x0a);
    }

    function buildLineBatches(lines, maxBatchBytes, maxLinesPerBatch) {
        const batches = [];
        let currentLines = [];
        let currentSize = 0;

        const flush = () => {
            if (currentLines.length === 0) return;
            batches.push({
                bytes: concatLines(currentLines),
                hasBlankLine: currentLines.some(isBlankLine),
            });
            currentLines = [];
            currentSize = 0;
        };

        for (const line of lines) {
            if (line.length > maxBatchBytes) {
                flush();
                batches.push({ bytes: line, hasBlankLine: isBlankLine(line) });
                continue;
            }

            const exceedsBytes = currentSize + line.length > maxBatchBytes;
            const exceedsLines = currentLines.length >= maxLinesPerBatch;

            if (currentLines.length > 0 && (exceedsBytes || exceedsLines)) {
                flush();
            }

            currentLines.push(line);
            currentSize += line.length;
        }

        flush();
        return batches;
    }

    async function writeBleChunk(characteristic, chunk) {
        if (characteristic.properties.write) {
            await characteristic.writeValue(chunk);
            return;
        }
        if (characteristic.properties.writeWithoutResponse) {
            await characteristic.writeValueWithoutResponse(chunk);
            return;
        }
        throw new Error('La impresora no admite escritura Bluetooth.');
    }

    async function sendBytes(characteristic, data, chunkSize, chunkDelay) {
        const chunks = buildBleChunks(data, chunkSize);
        for (const chunk of chunks) {
            await writeBleChunk(characteristic, chunk);
            await delay(chunkDelay);
        }
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

        const characteristic = bluetoothCharacteristic;
        const chunkSize = getBleChunkSize();
        const useWithoutResponse = !characteristic.properties.write
            && !!characteristic.properties.writeWithoutResponse;
        const chunkDelay = useWithoutResponse
            ? CHUNK_DELAY_WITHOUT_RESPONSE_MS
            : CHUNK_DELAY_WITH_RESPONSE_MS;

        const batches = buildLineBatches(splitIntoLines(bytes), BATCH_MAX_BYTES, BATCH_MAX_LINES);

        for (const batch of batches) {
            await sendBytes(characteristic, batch.bytes, chunkSize, chunkDelay);
            const pause = batch.hasBlankLine
                ? BATCH_PAUSE_MS + ITEM_SEPARATOR_EXTRA_MS
                : BATCH_PAUSE_MS;
            await delay(pause);
        }

        const tailDelay = Math.min(2800, 400 + batches.length * 14);
        await delay(tailDelay);
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
        const payload = {
            cafe_name: settings.cafeName || 'Artemisa Salón de Té',
        };
        if (orderData?.id && !orderData?.items?.length) {
            payload.order_id = orderData.id;
        } else {
            payload.order = orderData;
        }

        const response = await fetch('api/print.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
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
