# Guía completa — Artemisa Salón de Té

Documento de referencia con todos los pasos para desarrollo local, flujo de ramas Git, GitHub y despliegue en Hostgator.

**Repositorio:** https://github.com/richardrojascid/artemisa  
**Última actualización:** julio 2026

---

## Índice

1. [Resumen del flujo completo](#1-resumen-del-flujo-completo)
2. [Carpeta de trabajo en tu PC](#2-carpeta-de-trabajo-en-tu-pc)
3. [Desarrollo local (Windows)](#3-desarrollo-local-windows)
4. [Ramas Git: develop, master y features](#4-ramas-git-develop-master-y-features)
5. [Pull Requests y GitHub Actions](#5-pull-requests-y-github-actions)
6. [Protección de ramas en GitHub](#6-protección-de-ramas-en-github)
7. [Despliegue en Hostgator (sitio principal)](#7-despliegue-en-hostgator-sitio-principal)
8. [Actualizar producción después de cambios](#8-actualizar-producción-después-de-cambios)
9. [Errores frecuentes y soluciones](#9-errores-frecuentes-y-soluciones)
10. [Checklist rápido](#10-checklist-rápido)

---

## 1. Resumen del flujo completo

```
Tu PC (artemisa-app)
    │
    ├── feature/mi-cambio  ──PR──►  develop  ──PR──►  master  ──cPanel──►  Hostgator
    │                                  │                  │
    │                            integración          producción
    │                            pruebas local        selfie3dchile.com
    │
    └── scripts\serve-local.bat  →  http://127.0.0.1:8080
```

| Rama / entorno | Propósito |
|----------------|-----------|
| `feature/*` | Cambios nuevos en tu PC |
| `develop` | Integración y pruebas |
| `master` | Producción (Hostgator) |
| `main` | Histórico — **no usar para nuevos cambios** |

---

## 2. Carpeta de trabajo en tu PC

### Carpeta oficial del proyecto

```
C:\Users\Richard Rojas\artemisa-app
```

> **Importante:** No ejecutes comandos `git` desde `C:\Users\Richard Rojas` (carpeta de usuario). Siempre entra primero a `artemisa-app`.

### Clonar o actualizar por primera vez

```powershell
cd "C:\Users\Richard Rojas"
git clone https://github.com/richardrojascid/artemisa.git artemisa-app
cd artemisa-app
git checkout develop
git pull origin develop
```

### Verificar que el código está completo

```powershell
cd "C:\Users\Richard Rojas\artemisa-app"
Test-Path .\install.php   # debe devolver True
git branch                  # debe mostrar * develop
```

---

## 3. Desarrollo local (Windows)

### Requisitos

- **PHP 8.x** (vía XAMPP recomendado: `C:\xampp\php\php.exe`)
- Extensiones: `pdo_sqlite`, `zip`, `sqlite3`, `json`, `mbstring`, `curl`, `openssl`

### Iniciar servidor local

**PowerShell** (nota el `.\` al inicio):

```powershell
cd "C:\Users\Richard Rojas\artemisa-app"
.\scripts\serve-local.bat
```

**Alternativa directa con XAMPP:**

```powershell
cd "C:\Users\Richard Rojas\artemisa-app"
C:\xampp\php\php.exe -S localhost:8080 -t .
```

Deja la ventana **abierta** mientras pruebas.

### URLs locales

| Paso | URL |
|------|-----|
| Instalación (primera vez) | http://127.0.0.1:8080/install.php |
| Login | http://127.0.0.1:8080/login.php |
| Carta mesero | http://127.0.0.1:8080/index.php |
| Panel admin | http://127.0.0.1:8080/admin/ |

### Instalación local

1. Abre `install.php`
2. Define un PIN de 4–8 dígitos
3. Confirma e instala
4. Entra con el PIN en `login.php`

---

## 4. Ramas Git: develop, master y features

### Crear un cambio nuevo

```powershell
cd "C:\Users\Richard Rojas\artemisa-app"
git checkout develop
git pull origin develop
git checkout -b feature/nombre-del-cambio
```

Ejemplo:

```powershell
git checkout -b feature/nueva-categoria-cafes
```

### Guardar y subir cambios

```powershell
git add .
git commit -m "feat: descripción clara del cambio"
git push -u origin feature/nombre-del-cambio
```

### Abrir Pull Request hacia develop

1. Ve a https://github.com/richardrojascid/artemisa
2. **Pull requests → New pull request**
3. **Base:** `develop` ← **Compare:** `feature/nombre-del-cambio`
4. Espera el check verde: **Sintaxis y extensiones**
5. **Merge pull request**

### Actualizar tu PC después del merge

```powershell
cd "C:\Users\Richard Rojas\artemisa-app"
git checkout develop
git pull origin develop
```

### Pasar a producción (develop → master)

Cuando `develop` esté probado en local:

1. GitHub → **New pull request**
2. **Base:** `master` ← **Compare:** `develop`
3. Título sugerido: `release: despliegue producción YYYY-MM-DD`
4. Espera check verde → **Merge**

Luego en tu PC:

```powershell
git checkout master
git pull origin master
```

### Sincronizar ambas ramas en tu PC

```powershell
cd "C:\Users\Richard Rojas\artemisa-app"
git fetch origin
git checkout develop && git pull origin develop
git checkout master  && git pull origin master
```

---

## 5. Pull Requests y GitHub Actions

### Qué valida automáticamente cada PR a develop

- Extensiones PHP requeridas
- Sintaxis PHP (`php -l`) en todos los archivos `.php`

### Cuándo se ejecuta el CI

- Al abrir o actualizar un PR hacia `develop`
- Al hacer push directo a `develop`

### Si un PR queda bloqueado con "Waiting for status"

**Causa habitual:** el ruleset exige un check llamado "GitHub Actions" pero el workflow reporta **"Sintaxis y extensiones"**.

**Solución:**

1. GitHub → **Settings → Rules**
2. Edita el ruleset de `develop`
3. Quita el check "GitHub Actions"
4. Añade **"Sintaxis y extensiones"**
5. Guarda y refresca el PR

### Si un PR antiguo no dispara el CI

La rama del PR no incluye `.github/workflows/php-check.yml`. Solución:

```powershell
git checkout nombre-de-la-rama-del-pr
git merge origin/develop
git push origin nombre-de-la-rama-del-pr
```

---

## 6. Protección de ramas en GitHub

### Ruleset para develop

| Campo | Valor |
|-------|-------|
| Nombre | `Protección develop` |
| Enforcement | **Active** |
| Target | `develop` |
| Require pull request | ✅ Sí |
| Require status checks | ✅ **Sintaxis y extensiones** |
| Block force pushes | ✅ Sí |
| Restrict deletions | ✅ Sí |

### Ruleset para master (producción)

| Campo | Valor |
|-------|-------|
| Nombre | `Protección master producción` |
| Enforcement | **Active** |
| Target | `master` |
| Require pull request | ✅ Sí |
| Require status checks | ✅ **Sintaxis y extensiones** |
| Block force pushes | ✅ Sí |
| Restrict deletions | ✅ Sí |

---

## 7. Despliegue en Hostgator (sitio principal)

### Tu entorno Hostgator

| Dato | Valor |
|------|-------|
| Panel | **cPanel** (acceso vía Portal Hostgator → Manage → cPanel) |
| Usuario cPanel | `richar14` |
| Dominio principal | `selfie3dchile.com` |
| Carpeta del sitio | `public_html` |
| IP | `162.241.60.15` |
| PHP | `ea-php82` (PHP 8.2) |
| Correo | **Titan** (solo correo, no sube archivos) |

### Acceder a cPanel

**Opción A — URL directa:**

```
https://selfie3dchile.com:2083
```

**Opción B — Portal Hostgator:**

1. https://www.hostgator.com/my-account/login
2. **Hosting → Manage → cPanel**

### Antes de subir: respaldo (si ya hay sitio)

1. cPanel → **Archivos → Administrador de archivos**
2. Entra a `public_html`
3. Selecciona todo → **Comprimir** → descarga el ZIP de respaldo

### Paso 1 — Preparar archivos en tu PC

```powershell
cd "C:\Users\Richard Rojas\artemisa-app"
git checkout master
git pull origin master
```

Edita `includes\config.php`:

```php
define('MAIL_FROM', 'no-reply@selfie3dchile.com');
```

Crea un ZIP con:

- Carpetas: `admin`, `api`, `assets`, `data`, `includes`, `scripts`
- Archivos: `index.php`, `login.php`, `logout.php`, `install.php`, `.htaccess`, `manifest.json`
- **No incluir:** `.git`, `.github`, `data\cafe.db` (si el servidor ya tiene datos)

### Paso 2 — Subir por Administrador de archivos

Ruta: **cPanel → Archivos → Administrador de archivos**

1. Entra a **`public_html`**
2. **Configuración** → activa **Mostrar archivos ocultos (dotfiles)**
3. **Cargar** → sube `artemisa.zip`
4. Clic derecho en el ZIP → **Extraer**
5. Verifica estructura:

```
public_html/
├── admin/
├── api/
├── assets/
├── data/
├── includes/
├── index.php
├── login.php
├── install.php
└── .htaccess
```

6. Borra `artemisa.zip` del servidor

### Paso 3 — Configurar PHP

Ruta: **cPanel → Software → MultiPHP Manager**

1. Selecciona `selfie3dchile.com`
2. PHP **8.1** o **8.2** → **Apply**

> **Nota sobre extensiones:** El Editor INI de MultiPHP solo cambia memoria y uploads. Las extensiones (`pdo_sqlite`, `zip`, etc.) ya vienen instaladas en Hostgator. Para verificar, crea temporalmente `phpinfo.php` con `<?php phpinfo();` y búscalo en el navegador. **Bórralo después.**

### Paso 4 — Permisos de data/

En Administrador de archivos:

- Clic derecho en `data/` → **Permisos** → **755** (o **775** si da error de escritura)

### Paso 5 — Activar SSL

Ruta: **cPanel → Security → SSL/TLS Status**

1. Busca `selfie3dchile.com`
2. **Run AutoSSL** (Let's Encrypt gratuito)
3. Espera 5–15 minutos

### Paso 6 — Instalar en producción (primera vez)

```
https://selfie3dchile.com/install.php
```

1. Define PIN para el personal
2. Instala
3. Entra en `https://selfie3dchile.com/login.php`

### Paso 7 — Pruebas en producción

| Prueba | URL |
|--------|-----|
| Login | https://selfie3dchile.com/login.php |
| Carta | https://selfie3dchile.com/index.php |
| Admin | https://selfie3dchile.com/admin/ |
| Celular | misma URL con HTTPS |

### Correo (Titan)

1. cPanel → **Correo electrónico - desarrollado por Titan**
2. Crea `no-reply@selfie3dchile.com`
3. En admin de Artemisa configura correo de reportes

---

## 8. Actualizar producción después de cambios

### Flujo completo de un cambio a producción

```
1. feature/mi-cambio  →  PR a develop  →  merge
2. Probar en local (serve-local.bat)
3. PR develop → master  →  merge
4. git pull origin master en tu PC
5. Subir archivos a public_html en cPanel
6. Probar https://selfie3dchile.com/login.php
```

### Qué subir en una actualización

| Subir / reemplazar | No tocar |
|--------------------|----------|
| `admin/`, `api/`, `assets/`, `includes/` | `data/cafe.db` (base de datos con comandas) |
| `index.php`, `login.php`, etc. | `data/reports/` del servidor |
| `.htaccess`, CSS, JS | |

### Cron opcional — reporte diario

Ruta: **cPanel → Advanced → Cron Jobs**

```bash
php /home3/richar14/public_html/scripts/send-daily-report.php
```

(Ajusta la ruta si tu usuario o carpeta es distinta.)

---

## 9. Errores frecuentes y soluciones

### En tu PC (local)

| Problema | Solución |
|----------|----------|
| `scripts\serve-local.bat` no funciona en PowerShell | Usa `.\scripts\serve-local.bat` |
| `php no se reconoce` | Usa `C:\xampp\php\php.exe` |
| `Test-Path install.php` = False | Estás en carpeta equivocada; entra a `artemisa-app` |
| `git status` muestra todo tu usuario | Borra `C:\Users\Richard Rojas\.git` (solo la carpeta oculta .git) |
| Servidor corre pero no abre URL | `install.php` no existe en la carpeta; reclona el repo |

### En GitHub

| Problema | Solución |
|----------|----------|
| PR bloqueado "Waiting for status" | Cambia check a **Sintaxis y extensiones** en ruleset |
| PR antiguo sin CI | `git merge origin/develop` en la rama del PR y push |

### En Hostgator

| Problema | Solución |
|----------|----------|
| No veo extensiones en MultiPHP INI | Normal; ya vienen instaladas. Verifica con phpinfo o install.php |
| Página en blanco | PHP 8.x activo; revisa error_log en cPanel |
| Error permisos `/data` | Permisos 755 o 775 en `data/` |
| 404 en todas las URLs | `index.php` debe estar en `public_html`, no en subcarpeta |
| Pierdes comandas al subir | No sobrescribas `data/cafe.db` del servidor |
| FTP no conecta en Windows | Usa Administrador de archivos del cPanel |

---

## 10. Checklist rápido

### Desarrollo diario

- [ ] `cd artemisa-app`
- [ ] `git checkout develop && git pull`
- [ ] `git checkout -b feature/mi-cambio`
- [ ] Editar y probar con `.\scripts\serve-local.bat`
- [ ] `git commit` + `git push`
- [ ] PR a `develop` → check verde → merge

### Pasar a producción

- [ ] `develop` probado en local
- [ ] PR `develop` → `master` → merge
- [ ] `git pull origin master`
- [ ] Editar `config.php` si cambió correo
- [ ] ZIP y subir a `public_html`
- [ ] No sobrescribir `data/cafe.db`
- [ ] Probar `login.php`, `index.php`, `admin/`

### Primera instalación en Hostgator

- [ ] Respaldo de `public_html` si había sitio
- [ ] Subir archivos a `public_html`
- [ ] PHP 8.2 en MultiPHP Manager
- [ ] Permisos `data/` → 755
- [ ] SSL activo
- [ ] `install.php` → definir PIN
- [ ] Borrar `phpinfo.php` si lo creaste

---

## Referencias

| Documento | Contenido |
|-----------|-----------|
| `BRANCHING.md` | Estrategia de ramas resumida |
| `PRUEBA-LOCAL.md` | Instalación PHP en Windows |
| `README.md` | Descripción del proyecto |
| `MIGRACION.md` | Migración inicial desde repositorio export |

---

## Datos de contacto del proyecto

- **GitHub:** https://github.com/richardrojascid/artemisa
- **Producción:** https://selfie3dchile.com
- **Soporte Hostgator:** chat 24/7 en hostgator.com

---

*Documento generado para el equipo de desarrollo de Artemisa Salón de Té.*
