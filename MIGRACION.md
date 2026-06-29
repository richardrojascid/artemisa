# Artemisa — publicar código en GitHub

Repositorio oficial: **https://github.com/richardrojascid/artemisa**

El código completo está en la rama exportada:

**https://github.com/richardrojascid/JudgmentOfTheFallenWing/tree/artemisa**

---

## Opción A — Desde tu PC con Git (recomendada)

Abre **CMD** o **PowerShell** y ejecuta (te pedirá usuario/contraseña o token de GitHub):

```cmd
git clone https://github.com/richardrojascid/artemisa.git
cd artemisa
git remote add export https://github.com/richardrojascid/JudgmentOfTheFallenWing.git
git fetch export artemisa
git checkout -B main export/artemisa
git push -u origin main --force
```

Si prefieres usar la rama `develop` que ya creaste:

```cmd
git clone https://github.com/richardrojascid/artemisa.git
cd artemisa
git remote add export https://github.com/richardrojascid/JudgmentOfTheFallenWing.git
git fetch export artemisa
git checkout develop
git merge export/artemisa --allow-unrelated-histories -m "Importar sistema de comandas Artemisa"
git push origin develop
```

Luego en GitHub → **Settings** → **General** → pon `main` o `develop` como rama por defecto.

---

## Opción B — Descargar ZIP y subir manualmente

1. Descarga: https://github.com/richardrojascid/JudgmentOfTheFallenWing/archive/refs/heads/artemisa.zip  
2. Descomprime  
3. En la carpeta, abre terminal:

```cmd
git init
git remote add origin https://github.com/richardrojascid/artemisa.git
git add .
git commit -m "Sistema de comandas Artemisa Salón de Té"
git branch -M main
git push -u origin main --force
```

---

## Verificar

Abre https://github.com/richardrojascid/artemisa — debes ver carpetas `admin`, `api`, `assets`, `includes`, `index.php`, etc.

---

## Cerrar el PR antiguo

https://github.com/richardrojascid/JudgmentOfTheFallenWing/pull/5 → **Close pull request** (no merge).
