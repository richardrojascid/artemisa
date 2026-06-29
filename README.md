# Artemisa Salón de Té — Sistema de comandas para meseros

**Repositorio:** https://github.com/richardrojascid/artemisa

Sistema de comandas para **Artemisa Salón de Té**: carta 2026, PIN, propina flexible, reportes por correo e impresión térmica. Compatible con Hostgator (PHP + SQLite + HTTPS).

## Características

- **Carta Artemisa 2026**: Cafés, tés, saladas, pastelería, heladas y bebidas frías
- **Precios simple/doble** para cafés (como en la carta impresa)
- **Agregados y opciones**: leche vegetal, tetera, variantes de té, toppings, etc.
- **Acceso con PIN** para personal autorizado
- **Panel de administración** para editar menú, nombre del local y PIN
- **Total en tiempo real** del pedido
- **Impresión térmica** vía Bluetooth, red o navegador
- **Compatible con Hostgator** (PHP + SQLite + HTTPS)

## Instalación en Hostgator

1. Sube los archivos a `public_html`
2. Activa SSL (Let's Encrypt) en cPanel
3. Visita `https://tudominio.com/install.php`
4. Define un **PIN de 4-8 dígitos** y haz clic en instalar
5. Accede con tu PIN en `https://tudominio.com/login.php`

## Uso para meseros

1. Ingresa PIN en el celular
2. Indica **mesa** y **nombre del mesero**
3. Elige categoría y producto
4. Selecciona **Simple/Doble** (cafés), cantidad, agregados y notas
5. Envía la comanda → se guarda e imprime automáticamente

## Administración

Accede a `https://tudominio.com/admin/` (requiere PIN):

- Editar categorías y productos
- Cambiar nombre del local
- Cambiar PIN de acceso
- Restaurar carta Artemisa 2026 original

## Categorías de la carta 2026

| Categoría | Productos |
|-----------|-----------|
| Cafés | 15 variedades con precio simple/doble |
| Variedades de té | 14 opciones + variantes de hojas |
| Salados | Tostadas, planchado, pailas, pizza untable, etc. |
| Pastelería | Waffles, croissants, brownies, donuts, etc. |
| Helados | Copas, milkshake, frappuccino + extras |
| Bebidas frías | Iced coffee, limonada, jugos, etc. |

## Impresora térmica

- **Bluetooth (Android/Chrome)**: Configuración → Conectar impresora
- **Red WiFi**: Editar `includes/config.php` → `PRINTER_ENABLED = true`
- **Navegador**: Usa el servicio de impresión del sistema Android

## Estructura

```
├── login.php              # Acceso con PIN
├── index.php              # Vista del mesero
├── admin/index.php        # Panel de administración
├── api/                   # API menú, pedidos, impresión, admin
├── includes/
│   ├── ArtemisaMenuSeed.php  # Carta 2026 completa
│   └── ...
└── assets/                # CSS y JS responsivos
```

## Probar en local

Consulta **[PRUEBA-LOCAL.md](PRUEBA-LOCAL.md)** (incluye guía para Windows/XAMPP).

**Windows:** `scripts\serve-local.bat`  
**Linux/macOS:** `./scripts/serve-local.sh`

Abre: http://localhost:8080/install.php

## Licencia

MIT
