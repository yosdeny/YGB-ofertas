=== YGB Ofertas ===
Contributors: ygb
Tags: woocommerce, ofertas, popup, descuentos, marketing
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.7.10
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin para mostrar popups de ofertas de productos, compatible con WooCommerce y con el tema Astra.

== Description ==

YGB Ofertas permite mostrar un popup promocional con un producto concreto de tu tienda WooCommerce. Está pensado para destacar ofertas puntuales sin modificar el tema ni el núcleo de WordPress.

Características principales:

* Popup configurable desde el escritorio de WordPress (menú **YGB Ofertas**).
* Selección de un producto específico de WooCommerce mediante buscador AJAX (por nombre o ID).
* Personalización de diseño: color de botón, color de texto, color de fondo, color del overlay, ancho y animación (desvanecer, deslizar, zoom).
* Control de comportamiento: retraso de aparición, mostrar en cada visita, popup de salida (exit intent), popup al hacer scroll con porcentaje configurable y expiración de cookie.
* Programación por fechas: mostrar de inmediato o dentro de un rango de fecha/hora de inicio y fin.
* Segmentación por dispositivo: desactivar en móviles y/o tablets.
* Restricción por páginas: mostrar solo en páginas seleccionadas (inicio, tienda, carrito, finalizar compra, etc.).
* Lista de productos excluidos para impedir que aparezcan en el popup.
* Compatible con el tema Astra.

== Installation ==

1. Sube la carpeta `ygb-ofertas` al directorio `/wp-content/plugins/` o instala el plugin subiendo el archivo ZIP desde **Plugins → Añadir nuevo → Subir plugin**.
2. Activa el plugin en el menú **Plugins** de WordPress.
3. Asegúrate de que WooCommerce está instalado y activo (el plugin lo requiere para leer los productos).
4. Accede a **YGB Ofertas** en el menú lateral del escritorio para configurar el popup.

== Frequently Asked Questions ==

= ¿Necesito WooCommerce? =

Sí. El plugin usa las APIs de producto de WooCommerce (`wc_get_product`, `wc_price`) para obtener los datos que se muestran en el popup. Si WooCommerce no está activo, el plugin muestra un aviso y no carga sus funciones.

= ¿Dónde se guardan los ajustes? =

En la opción `ygb_ofertas_settings` de la tabla `wp_options` del sitio actual. La lista de productos excluidos se guarda en `ygb_ofertas_excluded_products`. En una instalación multisite, cada sitio tiene su propia configuración.

= ¿El popup se muestra a usuarios ya suscritos o que lo cerraron? =

Depende de la configuración. Con la cookie activa, el popup no vuelve a mostrarse hasta que expira (por defecto según el valor de "Expiración de la cookie"). Con la opción "Mostrar siempre" se ignora la cookie.

= ¿Cómo evito que un producto concreto aparezca en el popup? =

Tienes dos vías: la pestaña **Productos Excluidos** dentro de la configuración del plugin, o la casilla de exclusión en la pantalla de edición del propio producto.

= ¿Es compatible con temas distintos de Astra? =

Está desarrollado y probado con Astra, pero al usar hooks estándar de WordPress (`wp_enqueue_scripts`, `wp_footer`) debería funcionar con la mayoría de temas correctamente codificados.

== Screenshots ==

1. Pestaña General: estado del popup, título, descripción y texto del botón.
2. Pestaña Diseño: colores, animación y ancho del popup.
3. Pestaña Producto: buscador AJAX de productos de WooCommerce.
4. Pestaña Programación: rango de fechas y páginas específicas.

== Changelog ==

= 1.8.0 =
* En el modo **Solo una imagen** (sin producto) el alto de la imagen es libre: se quita el `max-height` fijo y el recorte (`object-fit: cover`) para que la imagen del popup se vea completa, sin cortes.
* La vista previa de la imagen en el panel de configuración tampoco limita el alto, por coherencia con lo que se verá en el popup.

= 1.7.10 =
* Corrección del error `ygb_ofertas is not defined`: el plugin se inicializa ahora en `init` (prioridad 0) en lugar de `plugins_loaded`, donde la clase `WooCommerce` aún podía no estar definida.
* Carga del textdomain reenganchada a `init` con prioridad 1 para que no quede sin ejecutar tras el cambio anterior.
* Implementada la restricción por páginas específicas (`specific_pages_only`), que hasta ahora se configuraba en el panel pero no tenía efecto: ahora limita la carga de assets y el render a inicio, tienda, carrito y finalizar compra.
* Normalización de la comprobación del estado del popup (`enabled`) para tolerar valores guardados como entero o cadena.
* Normalización en JavaScript de los ajustes booleanos y numéricos del popup, que podían llegar con tipos distintos según cómo los serializara `wp_localize_script`.
* Guarda contra `wc_get_product()` devolviendo `false` en el buscador AJAX de productos del panel.

= 1.7.9 =
* Endurecimiento de seguridad: escapado contextual de todas las salidas (`esc_html`, `esc_attr`, `esc_url`, `esc_textarea`, `esc_js`, `wp_kses_post`).
* Validación de colores HEX de 3 y 6 dígitos y de valores `rgba()` antes de inyectarlos en atributos de estilo.
* Validación estricta del formato `datetime-local` en las fechas de programación.
* Sanitización de la lista de productos excluidos como enteros únicos.
* Corrección de marcado HTML en la pestaña Producto.

= 1.7.8 =
* Mejoras en la selección de producto y en la vista previa del escritorio.

= 1.7.0 =
* Añadida programación por fechas y segmentación por dispositivo.

= 1.0.0 =
* Versión inicial.

== Upgrade Notice ==

= 1.7.10 =
Actualización recomendada: corrige el error que impedía mostrar el popup (`ygb_ofertas is not defined`) y activa la restricción por páginas específicas.

= 1.7.9 =
Actualización recomendada: corrige escapado de salidas y validación de ajustes.
