# Estrategia de ramas — Artemisa

Este documento define cómo trabajar con ramas, merge requests (pull requests) y despliegues a producción.

## Ramas del repositorio

| Rama | Propósito | Quién mergea aquí |
|------|-----------|-------------------|
| `develop` | Integración y desarrollo activo | Merge requests desde ramas de feature |
| `master` | Producción (Hostgator / servidor real) | Solo merge requests desde `develop` |
| `main` | Histórico del desarrollo inicial | **No usar para nuevos cambios** |

> **Nota:** `main` contiene el mismo código que `master` hoy. A partir de ahora el flujo oficial es `develop` → `master`.

## Flujo de trabajo

```
feature/mi-cambio  ──MR──►  develop  ──MR──►  master  ──►  Producción
```

### 1. Desarrollo diario (hacia `develop`)

```bash
cd "C:\Users\Richard Rojas\artemisa-final"
git checkout develop
git pull origin develop

# Crear rama para tu cambio
git checkout -b feature/nombre-del-cambio

# ... editar archivos ...

git add .
git commit -m "feat: descripción del cambio"
git push -u origin feature/nombre-del-cambio
```

En GitHub: **Pull requests → New pull request**
- **Base:** `develop`
- **Compare:** `feature/nombre-del-cambio`
- Revisar, aprobar y **Merge pull request**

### 2. Pasar a producción (hacia `master`)

Cuando `develop` esté estable y probado localmente:

```bash
git checkout develop
git pull origin develop
```

En GitHub: **New pull request**
- **Base:** `master`
- **Compare:** `develop`
- Título sugerido: `release: despliegue a producción YYYY-MM-DD`
- Tras el merge, desplegar en Hostgator desde `master`

### 3. Sincronizar tu PC después de un merge

```bash
git checkout develop
git pull origin develop

git checkout master
git pull origin master
```

## Configuración en GitHub (hacer una vez)

### A. Rama por defecto → `develop`

1. Ve a https://github.com/richardrojascid/artemisa/settings
2. **General → Default branch**
3. Cambia de `develop` a `develop` (confirmar que sea `develop`)
4. Guarda

Así los nuevos clones y PRs apuntan a desarrollo, no a producción.

### B. Proteger `master` (recomendado)

1. **Settings → Branches → Add branch ruleset** (o *Add rule*)
2. **Branch name pattern:** `master`
3. Activar:
   - ✅ Require a pull request before merging
   - ✅ Require approvals (opcional, 1 revisor)
4. Guardar

### C. Proteger `develop` (opcional)

Misma configuración con patrón `develop` si quieres evitar pushes directos accidentales.

## Despliegue en Hostgator (producción)

Tras merge a `master`:

1. En tu PC:
   ```bash
   git checkout master
   git pull origin master
   ```
2. Sube por FTP o cPanel los archivos a `public_html`
3. No subas la carpeta `data/` si ya tiene datos en el servidor
4. Verifica `includes/config.php` (correo, dominio)

## Comandos útiles

| Acción | Comando |
|--------|---------|
| Ver ramas | `git branch -a` |
| Cambiar de rama | `git checkout develop` |
| Estado actual | `git status` |
| Ver últimos commits | `git log --oneline -10` |
| Iniciar servidor local | `scripts\serve-local.bat` |

## Situación inicial del repositorio

- `develop` solo tenía el README inicial.
- `main` tenía todo el código del sistema.
- Se creó `master` como copia de producción.
- El PR **main → develop** sincroniza `develop` con el código completo.

Después de aprobar ese PR, trabaja siempre desde `develop` y promociona a `master` cuando vayas a producción.
