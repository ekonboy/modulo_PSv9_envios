# Módulo Impackta para PrestaShop 9

Módulo de envíos para integrar una tienda PrestaShop con Impackta. El módulo crea transportistas Impackta, guarda la configuración de conexión por tienda y expone un endpoint para que Impackta pueda consultar pedidos, leer productos y actualizar estados o tracking.

Autor: gabriel rese  
Versión: 2.0  
Fecha de revisión: 2026  
PHP probado en local: 8.3.26  
Compatibilidad objetivo: PrestaShop 9, con base compatible con estructura de módulos 1.7.8+

## Contexto de Entrega

Este proyecto parte de un módulo real de envíos Impackta ya existente. Se ha revisado, documentado y endurecido para mostrar criterio técnico sobre módulos PrestaShop sin rehacer desde cero un caso funcional distinto.

La decisión práctica ha sido entregar algo defendible y funcional: instalación limpia, desinstalación, back office propio, SQL separado, endpoint externo, validación básica, soporte multitienda coherente y documentación del uso de IA.

## Qué Hace El Módulo

- Crea la tabla propia `ps_impackta` con `id_shop`, `guid` e `idCliente`.
- Guarda una configuración independiente por tienda activa.
- Registra la pestaña de back office `AdminImpacktashipping` dentro de pedidos.
- Genera la URL completa que debe configurarse en el área de cliente de Impackta.
- Instala transportistas Impackta base:
  - Impackta 10 horas
  - Impackta 14 horas
  - Impackta 24 horas
  - Impackta Economy
  - Impackta Euro Parcel
- Expone el front controller `module/impackta/api`, que delega en `modules/impackta/portal/api.php`.
- Valida `codigoCliente` y `claveApi` antes de ejecutar acciones del endpoint.
- Permite a Impackta consultar pedidos, consultar un pedido concreto, listar productos, marcar pedidos como enviados o entregados, borrar tracking, actualizar tracking y aplicar un nuevo estado.

## Estructura Principal

- `modules/impackta/impackta.php`: clase principal del módulo. Contiene instalación, desinstalación, hooks, alta de pestaña BO y alta/limpieza de transportistas.
- `modules/impackta/controllers/admin/AdminImpacktashippingController.php`: pantalla de configuración del módulo en back office.
- `modules/impackta/controllers/front/api.php`: puente de PrestaShop hacia la API propia del portal.
- `modules/impackta/portal/connect.php`: carga parámetros de PrestaShop, abre conexión a base de datos y define helpers comunes.
- `modules/impackta/portal/api.php`: endpoint custom usado por Impackta.
- `modules/impackta/sql/install.php`: crea o migra la tabla propia del módulo.
- `modules/impackta/sql/uninstall.php`: elimina la tabla propia durante la desinstalación.
- `modules/impackta/views/`: assets y plantillas mínimas para cumplir la estructura del módulo.
- `modules/impackta/upgrade/`: estructura preparada para futuras migraciones.
- `IA.md`: documentación del uso de IA durante la revisión, ubicada en la raíz del repositorio.

## Instalación

1. Copiar la carpeta del módulo en `modules/impackta/` dentro de una instalación PrestaShop.
2. Instalarlo desde el back office.
3. Entrar en la pestaña Impackta o en configurar módulo.
4. Introducir el código de cliente proporcionado por Impackta.
5. Generar el `guid`.
6. Guardar la configuración.
7. Copiar la URL completa generada y pegarla en el área de cliente de Impackta como URL de tienda.

La URL incluye `idShop`, `codigoCliente` y `claveApi`, por lo que debe tratarse como credencial sensible.

## Buenas Prácticas Aplicadas

- Instalación y desinstalación completas mediante `install()` y `uninstall()`.
- Validación de la extensión `curl` antes de instalar.
- Registro explícito de hooks necesarios: `backOfficeHeader`, `displayBackOfficeHeader` y `updateCarrier`.
- Creación de una estructura mínima completa para evitar errores de instalación en PrestaShop.
- Uso de `modules/impackta/sql/install.php` y `modules/impackta/sql/uninstall.php` para aislar cambios de base de datos.
- Migración defensiva de la tabla si ya existía sin `id_shop`.
- Instalación idempotente de transportistas: antes de crear un carrier se comprueba si ya existe.
- Desinstalación más limpia: los transportistas del módulo se marcan como borrados e inactivos sin romper pedidos históricos.
- Alta de transportistas con grupos, zonas y rangos genéricos para que PrestaShop los considere válidos.
- Pantalla de configuración propia en back office en lugar de depender de formularios sueltos.
- Validación server-side de campos obligatorios y longitud en configuración.
- Uso de `pSQL`, `DbQuery` y sentencias preparadas en la API custom.
- Validación de credenciales externas antes de ejecutar acciones del endpoint.
- Endpoint externo separado en `modules/impackta/portal/api.php` para mantener clara la frontera entre módulo PS y comunicación con Impackta.
- Logs específicos de cambios de estado en `impackta_estado_log.txt`.
- Compatibilidad de conexión con `mysqli` y fallback PDO si el entorno no tiene la extensión.
- Carga de assets de back office solo en la configuración del módulo o su controlador admin.
- Comentarios añadidos en funciones personalizadas para facilitar mantenimiento por desarrolladores de módulos PS.

## Decisiones Técnicas

- Se mantiene un endpoint procedural en `modules/impackta/portal/api.php` porque la integración externa de Impackta ya consumía ese contrato y era preferible no romper compatibilidad.
- La configuración pasa a ser por tienda mediante `id_shop`; las URLs antiguas sin `idShop` siguen intentando leer una configuración disponible como fallback.
- Los carriers no se eliminan físicamente al desinstalar: se marcan como `deleted = 1` y `active = 0` para respetar pedidos históricos.
- Se usa jQuery empaquetado solo en el portal legacy. En el back office moderno se apoya en el jQuery ya disponible en PrestaShop.
- No se introduce Composer ni dependencias externas nuevas.

## Acciones Principales Del Endpoint

El endpoint acepta `tipo` como parámetro principal:

- `all_orders`: devuelve todos los pedidos.
- `orders`: devuelve pedidos entre `desde` y `hasta`.
- `order`: devuelve un pedido por `id` o `reference`.
- `enviado`: guarda tracking si se envía y cambia el pedido a Enviado.
- `entregado`: cambia el pedido a Entregado.
- `borrar`: borra tracking y restaura el estado anterior.
- `nuevo_estado`: cambia el pedido al estado indicado en `estado`.
- `actualizar_tracking`: actualiza solo el número de tracking.
- `products`: devuelve productos y combinaciones.
- `products_list`: muestra una tabla HTML auxiliar de productos.

## Fuera De Alcance

- No implementa badges visuales de producto, porque este módulo ya existía como integración de envíos Impackta.
- No se han añadido tests unitarios; la validación realizada ha sido lint de PHP y revisión manual del flujo.
- La estructura final del repositorio deja el módulo en `modules/impackta/`, como espera una entrega clonable para PrestaShop.
- No se ha eliminado el portal legacy porque podría estar en uso por instalaciones anteriores.

## Asunciones

- La tienda usará una URL generada desde back office, por lo que `idShop`, `codigoCliente` y `claveApi` estarán presentes.
- Si una instalación antigua usa una URL sin `idShop`, el módulo intenta resolver la primera configuración disponible.
- Los servicios de Impackta se mantienen por nombre comercial y se evitan duplicados por nombre de transportista.

## Verificación

- `php -l` ejecutado sobre todos los archivos PHP.
- Búsqueda de mojibake visible en archivos del módulo, excluyendo librería jQuery minificada.
- Revisión de metadatos de autor y fechas para dejarlos en 2026 y `gabriel rese`.

## Notas De Mantenimiento

- Si se cambian servicios de Impackta, actualizar `getCarrierDefinitions()` en `modules/impackta/impackta.php`.
- Si se modifican estados aceptados por la API, revisar `impacktaNormalizeOrderStateLabel()` e `impacktaFindStateId()`.
- Si se alteran parámetros externos, mantener compatibilidad con `id` y `reference`, porque el endpoint acepta ambos.
- Evitar editar assets minificados salvo que se sustituyan por una versión controlada.
- Probar siempre instalación, desinstalación y reinstalación para confirmar que la tabla, pestaña y transportistas no quedan duplicados.
