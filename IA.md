# Uso de IA en este proyecto

## 1. Herramientas utilizadas

| Herramienta | Versión / Modelo | Modo de uso | Aprox. % del trabajo |
|---|---|---|---|
| ChatGPT / Codex | GPT-5 Codex | Revisión del módulo, comentarios, README, IA.md y pequeños cambios de código | 55% |
| Revisión manual | Gabriel Rese | Decisiones de alcance, criterio de entrega y validación del resultado | 45% |

## 2. Configuración del proyecto

### CLAUDE.md / AGENTS.md

Ninguno. No se creó un archivo de instrucciones persistente a nivel proyecto porque el trabajo se hizo en una sesión puntual sobre un módulo ya existente.

### settings.json u otra configuración equivalente

Existe `.vscode/settings.json`, que contiene personalización visual del workspace. Se mantiene en el repositorio porque la prueba pide no ocultar configuración del entorno usada durante el trabajo. No contiene claves ni información sensible.

## 3. Skills personalizadas

Ninguna. No se usaron skills personalizadas del proyecto.

## 4. Slash commands personalizados

Ninguno. No se usaron comandos slash personalizados.

## 5. Sub-agentes invocados

No se invocaron sub-agentes. El trabajo se hizo en una única conversación asistida con Codex.

## 6. MCPs (Model Context Protocol)

| MCP | Para qué lo usaste | Qué aportó |
|---|---|---|
| Ninguno | No se conectó ningún MCP específico | Con más tiempo habría sido útil consultar documentación oficial de PrestaShop o revisar un repo base de módulos |

## 7. Prompts importantes

### Prompt 1

- **Herramienta:** ChatGPT / Codex
- **Prompt:** "haz un repaso del proyecto, de este, y comenta las funciones customizadas para los envíos de impackta..."
- **Qué generó:** Revisión del módulo, comentarios en funciones personalizadas y ampliación inicial del README.
- **Qué hice con el output:** Se aceptó parcialmente y se revisó con lint PHP.

### Prompt 2

- **Herramienta:** ChatGPT / Codex
- **Prompt:** "si, haz una pasada en archivos de readme.md y nada mas"
- **Qué generó:** README con acentos y caracteres UTF-8.
- **Qué hice con el output:** Se validó comprobando bytes UTF-8 porque PowerShell mostraba mojibake en consola.

### Prompt 3

- **Herramienta:** ChatGPT / Codex
- **Prompt:** "lee el documento de requisitos y dime qué falta para que coincida con lo que piden"
- **Qué generó:** Comparativa entre los requisitos recibidos y el estado real del módulo.
- **Qué hice con el output:** Se usó para decidir qué mejoras eran razonables sin rehacer el proyecto como otro caso funcional.

### Prompt 4

- **Herramienta:** ChatGPT / Codex
- **Prompt:** "a parte del tipo de modulo y version de PS, que me faltaria?"
- **Qué generó:** Lista de carencias técnicas: IA.md, README, id_shop, seguridad del endpoint, uninstall de carriers, mojibake y estructura de entrega.
- **Qué hice con el output:** Se priorizaron mejoras de bajo coste y alto impacto.

### Prompt 5

- **Herramienta:** ChatGPT / Codex
- **Prompt:** "añade id_Shop, mejora el readme con los nuevos cambios, limpia el mojibake visible, crea al ia.md y el punto 8..."
- **Qué generó:** Cambios de multitienda, validación de credenciales, limpieza de uninstall de carriers, README actualizado e IA.md.
- **Qué hice con el output:** Se revisó manualmente y se ejecutó lint PHP.

### Prompt 6

- **Herramienta:** ChatGPT / Codex
- **Prompt:** "muevelo a modules/impackta, despues de esto haz un repaso de los comentarios y del readme.md que todo cuadre y empezamos con github"
- **Qué generó:** Reestructuración del repositorio para dejar el módulo en `modules/impackta/` y ajuste de documentación.
- **Qué hice con el output:** Se verificó que las rutas relativas del módulo siguieran siendo válidas y se volvió a ejecutar lint PHP.

## 8. Errores de la IA que detecté

- **Qué generó la IA mal:** Al sustituir metadatos de autor, escapó accidentalmente `$this->author` como `$this\->author`.
- **Por qué estaba mal:** Rompía la sintaxis PHP del archivo principal del módulo.
- **Cómo lo corregí:** Se detectó con `php -l modules/impackta/impackta.php` y se corrigió a `$this->author`.

- **Qué generó la IA mal:** En un primer README se evitaban acentos por prudencia de codificación.
- **Por qué estaba mal:** El README debía ser legible en español y el usuario pidió expresamente usar acentos y `ñ`.
- **Cómo lo corregí:** Se reescribió `Readme.md` en UTF-8 y se comprobaron bytes para confirmar que el archivo estaba bien guardado.

- **Qué generó la IA mal:** Inicialmente la documentación no explicaba con suficiente claridad que el módulo partía de una integración de envíos ya existente.
- **Por qué estaba mal:** Podía parecer que se intentaba cumplir literalmente otro caso funcional, cuando en realidad se entrega un módulo ya existente.
- **Cómo lo corregí:** Se añadió una sección de contexto de entrega y fuera de alcance en el README.

- **Qué generó la IA incompleto:** La primera revisión comentaba funciones y README, pero no añadía soporte multitienda real.
- **Por qué estaba incompleto:** La prueba valora que el módulo no rompa en multitienda y la tabla original era global.
- **Cómo lo corregí:** Se añadió `id_shop`, consultas por tienda, URL con `idShop` y fallback para URLs antiguas.

- **Qué generó la IA incompleto:** El endpoint usaba `codigoCliente` en logs, pero no bloqueaba llamadas con credenciales incorrectas.
- **Por qué estaba mal:** La URL contiene credenciales y deben comprobarse antes de devolver pedidos o cambiar estados.
- **Cómo lo corregí:** Se añadió validación de `codigoCliente` y `claveApi` contra la configuración guardada.

- **Qué generó la IA incompleto:** La desinstalación eliminaba tabla y pestaña, pero dejaba transportistas activos.
- **Por qué estaba mal:** La prueba valora una desinstalación limpia sin residuos funcionales.
- **Cómo lo corregí:** Se añadió limpieza de carriers marcándolos como `deleted` e inactivos sin borrar históricos.

## 9. Partes que NO usé IA

La decisión de aprovechar el módulo real de Impackta es manual. También es manual el criterio de aceptar una entrega honesta: documentar diferencias, justificar decisiones y reforzar el módulo existente en vez de simular otro proyecto.

## 10. Reflexión final

- **Qué me ahorró la IA:** Lectura rápida del proyecto, localización de funciones custom, generación de documentación base y detección de carencias frente a la prueba.
- **En qué me entorpeció:** Algunas sustituciones mecánicas fueron demasiado agresivas y hubo que corregir sintaxis y metadatos.
- **Qué cambiaría si lo repitiera:** Primero fijaría el alcance exacto de entrega y después haría commits pequeños: documentación, seguridad endpoint, multitienda, uninstall y limpieza visual por separado.
