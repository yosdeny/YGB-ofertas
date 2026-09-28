=== YGB Ofertas ===
Contributors: ygb
Tags: woocommerce, ofertas, popup, descuentos, marketing
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.8.3
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
* Dos tipos de contenido: producto de WooCommerce o solo una imagen (ambos con botón opcional).
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

= ¿Puedo usar HTML en la descripción del popup? =

Sí. El campo **Descripción** de la pestaña General admite HTML limitado a la allowlist de WordPress: `strong`, `em`, `b`, `i`, `a`, `br`, `ul`, `ol`, `li`, `p`, `span`, `h1`–`h6`, `blockquote`, etc. Al guardar se filtra con `wp_kses_post()` y al mostrarlo se aplica `wpautop()` (convierte saltos de línea en párrafos) más un segundo `wp_kses_post()` como defensa en profundidad. No se permiten atributos `style` inline ni etiquetas `script`, `iframe`, `object`, `form`; tampoco eventos `on*` ni `javascript:` en `href`.

== Screenshots ==

1. Pestaña General: estado del popup, título, descripción y texto del botón.
2. Pestaña Diseño: colores, animación y ancho del popup.
3. Pestaña Producto: buscador AJAX de productos de WooCommerce y modo "Solo una imagen".
4. Pestaña Programación: rango de fechas y páginas específicas.

== Changelog ==

= 1.8.3 =
* El campo **Descripción** de la pestaña General admite ahora HTML. Al guardar se filtra con `wp_kses_post()` (allowlist estándar de WordPress: `strong`, `em`, `a`, `br`, `ul`, `li`, `p`, `h1`–`h6`, etc.) en lugar de `sanitize_textarea_field()`, que eliminaba todo el marcado y dejaba el campo inservible para dar formato.
* En el frontend la descripción se renderiza con `wpautop( wp_kses_post( $descripcion ) )`: `wpautop()` convierte los saltos de línea del texto plano en párrafos y `wp_kses_post()` vuelve a filtrar como defensa en profundidad (por si el valor llega modificado por migración, import o SQL directo).
* Retirado el envoltorio `<p>` fijo del bloque `.popup-description` en `templates/popup-template.php`: ahora el usuario controla los bloques y `wpautop` ya añade los `<p>` necesarios para texto plano.
* No se permiten atributos `style` inline ni etiquetas `script`, `iframe`, `object`, `form`; tampoco eventos `on*` ni `javascript:` en `href`. Si necesitas colores o tipografías personalizadas, la vía correcta es añadir una clase al contenedor `.popup-description` y definirla en el tema.
* El `<textarea>` del panel pasa a `rows="6"` y `class="large-text code"` (fuente monoespaciada) para editar HTML con comodidad. El texto de ayuda de la fila indica qué etiquetas están permitidas.
* Retrocompatibilidad: las descripciones guardadas en texto plano se siguen viendo correctamente porque `wpautop` las convierte en párrafos. Las descripciones que ya contuvieran HTML (antes se veían como texto literal porque `esc_html` las escapaba) ahora se renderizan como HTML, que es el comportamiento esperado.
* Unificada la versión en todos los ficheros a 1.8.3 (header, constante `YGB_OFERTAS_VERSION`, `admin.js`, `popup.js` y este readme).

= 1.8.2 =
* `uninstall.php` reescrito con prefijos EXCLUSIVOS del plugin (`ygb_ofertas_` / `_ygb_ofertas_`): al desinstalar ya no se borran opciones, transients, metadata, roles ni capacidades de otros plugins que pudieran compartir el prefijo genérico `ygb_`. La limpieza de multisite ahora pagina en lotes de 500 sitios en lugar de usar un tope fijo.
* Corregida la pérdida de decimales en "Retraso en la visualización": `popup.js` usa `parseFloat` en lugar de `parseInt`, así que un valor de 5.5 s se respeta.
* Corregida la división por cero en el trigger de scroll: si la página cabe entera en el viewport, el popup ya no se dispara sin que el usuario haya hecho scroll.
* Cookie `ygb_ofertas_shown` con `SameSite=Lax` para evitar su envío en peticiones cross-site.
* Eliminado el aviso de administración muerto que leía `settings-updated` (los ajustes se guardan por AJAX, nunca llega ese parámetro).
* Simplificada la carga del textdomain: se llama directamente desde el constructor en lugar de registrar un callback extra a `init` que podía no dispararse.
* `admin_enqueue_scripts()` usa coincidencia exacta de hook en lugar de `str_contains`, para no cargar assets en pantallas ajenas con ese substring.
* Accesibilidad del popup: `role="dialog"`, `aria-modal="true"`, `aria-hidden`, `aria-label` traducible y `type="button"` en los botones de cierre. El símbolo `×` queda como decorativo (`aria-hidden`).
* En el modo "Solo una imagen" ya no se renderiza el bloque de nombre y precio del producto: la plantilla condiciona `.popup-header` y `.popup-product` al tipo `product`. El título del adjunto se sigue usando solo como atributo `alt` de la imagen.
* Imagen responsiva en modo imagen: se dimensiona contra el ancho en píxeles del popup (variable `--ygb-popup-width`) en lugar de un 100% relativo; mantiene la proporción original sin recortes ni deformaciones.
* Imagen de producto en un marco cuadrado de 300×300 px en escritorio (pequeño ajuste en móvil), con `object-fit: contain`, centrada y sin recortes.
* Precio del producto en color rojo (`#ff0000`).
* Unificada la versión en todos los ficheros a 1.8.2 (antes había discrepancias entre el header, la constante, `admin.js`, `popup.js` y este readme).

= 1.8.1 =
* Eliminada la opción "Imagen + texto propio" del tipo de contenido del popup: su título y su texto se mezclaban con el título y la descripción generales del popup y resultaba confuso. El popup vuelve a ofrecer dos tipos de contenido: producto de WooCommerce o solo una imagen (ambos con botón opcional).
* Migración automática: las instalaciones que tenían activo ese modo pasan a "Solo una imagen" si había una imagen guardada; si no, vuelven al modo producto. Los campos `custom_title` y `custom_text` dejan de leerse, guardarse y renderizarse.

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

= 1.8.3 =
* La Descripción del popup admite ahora HTML limitado a la allowlist de WordPress (`strong`, `em`, `a`, `br`, `ul`, `li`, `p`...). Los sitios que tuvieran HTML en ese campo empezarán a verlo renderizado en lugar de como texto escapado. No requiere ninguna acción por parte del usuario: la migración es transparente.
* No se permiten atributos `style` inline; si necesitas colores personalizados, añade una clase al contenedor `.popup-description` en tu tema.

= 1.8.2 =
* Corregido el `uninstall.php`: usa prefijos exclusivos del plugin para no borrar datos de otros plugins al desinstalar. Si vas a eliminar YGB Ofertas, actualiza primero a esta versión.
* Diversas correcciones de robustez en el frontend (decimales del delay, scroll sin overflow, SameSite en la cookie).
* Mejoras de accesibilidad y de maquetación del popup.

= 1.8.1 =
* Eliminada la opción "Imagen + texto propio" del tipo de contenido del popup: su título y su texto se mezclaban con el título y la descripción generales del popup y resultaba confuso. El popup vuelve a ofrecer dos tipos de contenido: producto de WooCommerce o solo una imagen (ambos con botón opcional).
* Migración automática: las instalaciones que tenían activo ese modo pasan a "Solo una imagen" si había una imagen guardada; si no, vuelven al modo producto. Los campos `custom_title` y `custom_text` dejan de leerse, guardarse y renderizarse.