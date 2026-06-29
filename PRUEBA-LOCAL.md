# Cómo probar localmente

## Requisitos

- **PHP 7.4+** (recomendado 8.x)
- Extensiones: `pdo_sqlite`, `zip`, `json`

---

## Instalar PHP en Windows (paso a paso)

Tienes dos opciones: **PHP directo** (ligero) o **XAMPP** (más fácil si nunca has usado PHP).

### Opción A — PHP directo (recomendada)

#### 1. Descargar PHP

1. Abre [https://windows.php.net/download/](https://windows.php.net/download/)
2. En **PHP 8.3** o **8.4**, descarga el ZIP:
   - **VS16 x64 Thread Safe** (ej. `php-8.3.x-Win32-vs16-x64.zip`)

#### 2. Extraer en una carpeta

1. Crea la carpeta `C:\php`
2. Extrae ahí todo el contenido del ZIP

#### 3. Configurar `php.ini`

1. En `C:\php`, copia `php.ini-development` y renómbralo a **`php.ini`**
2. Abre `php.ini` con el Bloc de notas
3. Busca y **descomenta** (quita el `;` del inicio) estas líneas:

```ini
extension_dir = "ext"

extension=curl
extension=mbstring
extension=openssl
extension=pdo_sqlite
extension=sqlite3
extension=zip
```

4. Guarda el archivo

#### 4. Agregar PHP al PATH de Windows

1. Presiona `Win + R`, escribe `sysdm.cpl` y Enter
2. Pestaña **Opciones avanzadas** → **Variables de entorno**
3. En **Variables del sistema**, selecciona **Path** → **Editar**
4. **Nuevo** → escribe `C:\php` → Aceptar en todas las ventanas

#### 5. Verificar instalación

Abre **Símbolo del sistema** o **PowerShell** (nueva ventana) y ejecuta:

```cmd
php -v
```

Debe mostrar la versión de PHP. Si dice "no se reconoce", cierra y abre de nuevo la terminal o reinicia el PC.

#### 6. Iniciar la aplicación

```cmd
cd C:\ruta\a\tu\proyecto
scripts\serve-local.bat
```

O manualmente:

```cmd
cd C:\ruta\a\tu\proyecto
php -S localhost:8080 -t .
```

Abre el navegador en: **http://localhost:8080**

---

### Opción B — XAMPP (más simple)

1. Descarga XAMPP desde [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. Instala con Apache y PHP marcados
3. Copia la carpeta del proyecto a `C:\xampp\htdocs\artemisa`
4. Abre XAMPP Control Panel → inicia **Apache**
5. Visita: **http://localhost/artemisa/install.php**

> Con XAMPP no necesitas `php -S`; Apache sirve los archivos directamente.

---

### Probar en el celular (Windows + misma WiFi)

1. En CMD ejecuta `ipconfig` y anota tu **IPv4** (ej. `192.168.1.45`)
2. En el celular abre: `http://192.168.1.45:8080/login.php`
3. Si no conecta, en Windows Firewall permite el puerto **8080**:
   - Panel de control → Firewall → Configuración avanzada → Reglas de entrada → Nueva regla → Puerto TCP 8080

---

## Linux / macOS

**Ubuntu / Debian:**
```bash
sudo apt update
sudo apt install php php-sqlite3 php-zip
```

**macOS (Homebrew):**
```bash
brew install php
```

**Iniciar servidor:**
```bash
chmod +x scripts/serve-local.sh
./scripts/serve-local.sh
```

---

## Pasos de prueba

### 1. Instalar
1. Ve a `http://localhost:8080/install.php`
2. Define un PIN (ej. `1234`) y confirma
3. Se carga la carta Artemisa 2026

### 2. Login (logo + PIN)
1. Ve a `http://localhost:8080/login.php`
2. Verás el **logo de Artemisa** y el teclado numérico
3. Ingresa tu PIN

### 3. Tomar pedidos (mesero)
1. En `http://localhost:8080/index.php` elige productos
2. En el carrito verás:
   - **Subtotal productos**
   - **Propina 10%** (marcada por defecto)
   - **Otra propina (CLP)** — campo manual para otro monto
3. Si escribes un monto manual, se desmarca el 10% y se usa tu valor
4. Si vuelves a marcar el 10%, se borra el campo manual
5. Envía la comanda

### 4. Reporte de ventas
1. Entra a `http://localhost:8080/admin/`
2. **Ver resumen** / **Descargar CSV** / **Enviar por correo**
3. En local el correo suele guardarse en `data/reports/`

---

## Producción en Hostgator

1. Sube archivos a `public_html`
2. Activa SSL (HTTPS)
3. Edita `includes/config.php` → `MAIL_FROM` con correo de tu dominio
4. En admin configura el correo de reportes
5. Opcional: cron con `scripts/send-daily-report.php`

---

## Solución de problemas (Windows)

| Problema | Solución |
|----------|----------|
| `php no se reconoce` | Agrega `C:\php` al PATH y abre nueva terminal |
| Error `could not find driver` | Habilita `extension=pdo_sqlite` en `php.ini` |
| Error al generar ODT | Habilita `extension=zip` en `php.ini` |
| No abre desde el celular | Revisa firewall y que PC y celular estén en la misma WiFi |
| Carpeta `data` sin permisos | Clic derecho en `data` → Propiedades → quitar solo lectura |
| Correo no llega en local | Normal; revisa `data/reports/` |
