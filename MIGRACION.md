# Migración desde JudgmentOfTheFallenWing

Este proyecto vivía en el repositorio `JudgmentOfTheFallenWing` (PR #5).  
Ahora es un **repositorio independiente**: `artemisa`.

## Si aún no existe el repo en GitHub

1. Entra a https://github.com/new  
2. Nombre del repositorio: **`artemisa`**  
3. Público o privado, **sin** README ni .gitignore (vacío)  
4. Clic en **Create repository**

## Subir este código a `richardrojascid/artemisa`

En tu PC, después de clonar o con esta carpeta:

```bash
git remote add artemisa https://github.com/richardrojascid/artemisa.git
git branch -M main
git push -u artemisa main
```

## Descargar sin clonar todo JudgmentOfTheFallenWing

**Opción A** — Rama exportada (mientras migras):

https://github.com/richardrojascid/JudgmentOfTheFallenWing/tree/artemisa

ZIP: https://github.com/richardrojascid/JudgmentOfTheFallenWing/archive/refs/heads/artemisa.zip

**Opción B** — Repo definitivo (cuando exista):

https://github.com/richardrojascid/artemisa

## Cerrar el PR antiguo

En JudgmentOfTheFallenWing, cierra el **Pull Request #5** con un comentario:

> Código migrado al repositorio `artemisa`. Este PR ya no aplica.
