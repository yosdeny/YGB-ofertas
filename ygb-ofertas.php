<?php
/**
 * Plugin Name:       YGB Ofertas
 * Description:       Plugin para mostrar popups de ofertas de productos compatible con el tema Astra.
 * Version:           1.8.2
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            YGB
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ygb-ofertas
 * Domain Path:       /languages
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes
define('YGB_OFERTAS_VERSION', '1.8.2');
define('YGB_OFERTAS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('YGB_OFERTAS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Clase principal del plugin
class YGB_Ofertas {
    
    private static $instance = null;
    private $settings = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();

        // La instancia se crea dentro de 'init' (prioridad 0), así que
        // 'init' ya está en marcha: llamamos al textdomain directamente en
        // lugar de registrar otro callback a 'init' que podría no llegar a
        // dispararse.
        $this->init();
    }
    
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'render_popup'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_ajax_save_popup_stats', array($this, 'save_popup_stats'));
        add_action('wp_ajax_ygb_search_products', array($this, 'ajax_search_products'));
        add_action('wp_ajax_ygb_save_tab', array($this, 'ajax_save_tab'));
        
        // Añadir meta box para activar/desactivar por producto
        add_action('add_meta_boxes', array($this, 'add_product_metabox'));
        add_action('save_post_product', array($this, 'save_product_metabox'));
    }
    
    public function init() {
        load_plugin_textdomain('ygb-ofertas', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function admin_enqueue_scripts($hook) {
        // Match exacto por hook. Evita cargar assets en pantallas que
        // contengan 'ygb-ofertas' como substring en otro contexto.
        $allowed_hooks = array(
            'toplevel_page_ygb-ofertas',
            'ygb-ofertas_page_ygb-ofertas-excluded',
        );

        if (!in_array($hook, $allowed_hooks, true)) {
            return;
        }
        
        wp_enqueue_script('ygb-ofertas-admin', YGB_OFERTAS_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), YGB_OFERTAS_VERSION, true);
        wp_localize_script('ygb-ofertas-admin', 'ygb_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ygb_admin_nonce'),
            'search_placeholder' => __('Buscar productos...', 'ygb-ofertas'),
            'saving_text' => __('Guardando...', 'ygb-ofertas'),
            'saved_text' => __('¡Guardado!', 'ygb-ofertas'),
            'error_text' => __('Error al guardar', 'ygb-ofertas')
        ));

        // Selector de medios solo donde existe el campo de imagen (pestilla Producto).
        if ('toplevel_page_ygb-ofertas' === $hook) {
            wp_enqueue_media();

            wp_localize_script('ygb-ofertas-admin', 'ygb_media', array(
                'title' => __('Seleccionar imagen para el popup', 'ygb-ofertas'),
                'button' => __('Usar esta imagen', 'ygb-ofertas')
            ));
        }
    }
    
    public function enqueue_scripts() {
        // Cargar scripts si el popup PUEDE mostrarse (ignorando cookies)
        if ($this->can_display_popup()) {
            wp_enqueue_style('ygb-ofertas-css', YGB_OFERTAS_PLUGIN_URL . 'assets/css/popup.css', array(), YGB_OFERTAS_VERSION);
            wp_enqueue_script('ygb-ofertas-js', YGB_OFERTAS_PLUGIN_URL . 'assets/js/popup.js', array('jquery'), YGB_OFERTAS_VERSION, true);
            
            $settings = $this->get_popup_settings();
            
            wp_localize_script('ygb-ofertas-js', 'ygb_ofertas', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ygb_ofertas_nonce'),
                'settings' => $settings
            ));
        }
    }
    
    public function render_popup() {
        // Mostrar el popup solo si DEBE mostrarse AHORA (considerando cookies)
        if (!$this->should_display_popup()) {
            return;
        }

        $producto = $this->get_popup_content();

        if (!$producto) {
            return;
        }

        $template = YGB_OFERTAS_PLUGIN_DIR . 'templates/popup-template.php';

        // Ruta construida con constante interna (no input de usuario), pero se
        // verifica igualmente para evitar un fatal error si el archivo falta.
        if (!is_readable($template)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                _doing_it_wrong(
                    __METHOD__,
                    esc_html__('No se encontró la plantilla popup-template.php.', 'ygb-ofertas'),
                    '1.7.9'
                );
            }
            return;
        }

        $settings = $this->get_popup_settings();
        // Tipo de popup actual ('product', 'image' o 'custom'): la plantilla lo
        // usa para adaptar el estilo de la imagen (en 'image' el alto es libre).
        $popup_type = isset($settings['popup_type']) ? (string) $settings['popup_type'] : 'product';
        if (!in_array($popup_type, ['product', 'image', 'custom'], true)) {
            $popup_type = 'product';
        }
        include $template;
    }
    
    /**
     * Verifica si el popup PUEDE mostrarse (ignorando cookies)
     * Esta función se usa para cargar los scripts
     */
    private function can_display_popup() {
        $settings = $this->get_popup_settings();
        
        // Verificar si el popup está activado. Cast a string: los ajustes
        // pueden guardarse como entero (1) y la comparación estricta fallaría.
        if (!isset($settings['enabled']) || '1' !== (string) $settings['enabled']) {
            return false;
        }
        
        // En modo producto se exige un producto valido y no excluido.
        // En el modo 'image' la validacion la hace get_custom_content(): basta
        // con que haya una imagen de la biblioteca; el enlace del boton es
        // opcional.
        $type = isset($settings['popup_type']) ? (string) $settings['popup_type'] : 'product';

        if ('product' !== $type) {
            if (null === $this->get_custom_content($settings)) {
                return false;
            }
        } else {
            if (empty($settings['selected_product'])) {
                return false;
            }

            $product = wc_get_product(intval($settings['selected_product']));
            if (!$product) {
                return false;
            }

            if ($this->is_product_excluded(intval($settings['selected_product']))) {
                return false;
            }
        }
        
        // Verificar programación de fechas
        $popup_status = isset($settings['popup_status']) ? (string) $settings['popup_status'] : 'immediate';
        if ('scheduled' === $popup_status && !empty($settings['start_date']) && !empty($settings['end_date'])) {
            // time() en lugar de current_time('timestamp'): este último está
            // deprecado desde WP 5.3 y aplicaba el offset del sitio, produciendo
            // comparaciones erróneas contra strtotime().
            $now = time();
            $start = strtotime($settings['start_date']);
            $end = strtotime($settings['end_date']);
            
            if ($now < $start || $now > $end) {
                return false;
            }
        }

        // Verificar restricción de páginas específicas.
        if (!$this->is_current_page_allowed()) {
            return false;
        }
        
        return true;
    }

    /**
     * Comprueba si el popup está permitido en la página actual.
     *
     * Solo se evalúa si 'specific_pages_only' está activo. La lista de páginas
     * es una allowlist cerrada definida en el panel (all, home, shop, cart,
     * checkout); cualquier otro valor se ignora.
     */
    private function is_current_page_allowed() {
        $settings = $this->get_popup_settings();

        $specific_pages_only = isset($settings['specific_pages_only']) ? (string) $settings['specific_pages_only'] : '0';
        if ('1' !== $specific_pages_only) {
            return true;
        }

        $allowed = is_array($settings['pages']) ? array_map('sanitize_key', $settings['pages']) : [];

        if (empty($allowed) || in_array('all', $allowed, true)) {
            return true;
        }

        $current = $this->get_current_page_context();

        if ('' === $current) {
            return false;
        }

        return in_array($current, $allowed, true);
    }

    /**
     * Devuelve un identificador de la página actual alineado con la allowlist
     * del panel, o cadena vacía si no coincide con ninguna.
     *
     * Las condicionales de WooCommerce requieren que WooCommerce esté cargado;
     * se comprueban antes de invocarlas para no provocar errores fatales.
     */
    private function get_current_page_context() {
        if (is_front_page() || is_home()) {
            return 'home';
        }

        if (!function_exists('is_woocommerce')) {
            return '';
        }

        if (is_shop()) {
            return 'shop';
        }

        if (function_exists('is_cart') && is_cart()) {
            return 'cart';
        }

        if (function_exists('is_checkout') && is_checkout()) {
            return 'checkout';
        }

        return '';
    }
    
    /**
     * Verifica si el popup DEBE mostrarse AHORA (considerando cookies)
     * Esta función se usa para renderizar el popup
     */
    private function should_display_popup() {
        if (!$this->can_display_popup()) {
            return false;
        }
        
        $settings = $this->get_popup_settings();

        // La cookie llega del navegador: sanitizar antes de comparar.
        $cookie_shown = isset($_COOKIE['ygb_ofertas_shown'])
            ? sanitize_key(wp_unslash($_COOKIE['ygb_ofertas_shown']))
            : '';

        $show_always = isset($settings['show_always']) ? (string) $settings['show_always'] : '0';
        if ('1' === $cookie_shown && '1' !== $show_always) {
            return false;
        }
        
        return true;
    }

    /**
     * Comprueba si un producto está en la lista de excluidos.
     * Comparación estricta sobre enteros para evitar falsos positivos.
     */
    private function is_product_excluded($product_id) {
        $excluded = get_option('ygb_ofertas_excluded_products', array());

        if (!is_array($excluded)) {
            return false;
        }

        return in_array((int) $product_id, array_map('intval', $excluded), true);
    }
    
    public function get_popup_settings() {
        if ($this->settings !== null) {
            return $this->settings;
        }
        
        $defaults = array(
            'enabled' => '0',
            'title' => '¡Oferta Especial!',
            'description' => '',
            'button_text' => 'Ver Producto',
            'button_color' => '#007cba',
            'text_color' => '#333333',
            'background_color' => '#ffffff',
            'overlay_color' => 'rgba(0,0,0,0.7)',
            'display_delay' => '5',
            'show_on_exit' => '0',
            'show_on_scroll' => '0',
            'scroll_percentage' => '50',
            'show_always' => '0',
            'selected_product' => '',
            'animation' => 'fade',
            'width' => '500',
            'close_button' => '1',
            'show_close_after' => '0',
            'cookie_expiration' => '1',
            'popup_status' => 'immediate',
            'start_date' => '',
            'end_date' => '',
            'specific_pages_only' => '0',
            'pages' => array('all'),
            'mobile_disabled' => '0',
            'tablet_disabled' => '0',
            // Tipo de popup: 'product' (producto WooCommerce) o 'image' (solo
            // una imagen de la biblioteca).
            'popup_type' => 'product',
            'custom_image_id' => '0',
            // Enlace opcional del modo imagen: URL absoluta o '#id' para anclar
            // a una seccion de la pagina actual. Vacio = sin boton.
            'custom_link' => ''
        );
        
        $saved_settings = get_option('ygb_ofertas_settings', array());
        
        if (empty($saved_settings)) {
            $this->settings = $defaults;
            update_option('ygb_ofertas_settings', $defaults);
            return $this->settings;
        }
        
        $this->settings = wp_parse_args($saved_settings, $defaults);
        
        if (!isset($this->settings['pages']) || !is_array($this->settings['pages'])) {
            $this->settings['pages'] = array('all');
        }
        
        // Compatibilidad: la opcion "Imagen + texto propio" ('custom') se ha
        // eliminado porque su titulo y su texto se mezclaban con el titulo y la
        // descripcion generales del popup y resultaba confuso. Las instalaciones
        // que la tenian activa pasan al modo "Solo una imagen"; si no habia
        // imagen guardada, vuelven al modo producto.
        $popup_type = (string) ($this->settings['popup_type'] ?? '');

        if ('custom' === $popup_type) {
            $this->settings['popup_type'] = absint($this->settings['custom_image_id'] ?? 0) > 0 ? 'image' : 'product';
        } elseif (!in_array($popup_type, ['product', 'image'], true)) {
            $this->settings['popup_type'] = 'product';
        }

        return $this->settings;
    }
    
    /**
     * Devuelve el enlace del boton del popup segun el tipo configurado.
     *
     * En modo producto es la URL del producto; en el modo imagen es el enlace
     * opcional introducido a mano (URL absoluta o ancla local).
     * Cadena vacia = el popup se muestra sin boton.
     */
    private function get_popup_button_link($settings) {
        $type = isset($settings['popup_type']) ? (string) $settings['popup_type'] : 'product';

        if ('product' === $type) {
            return esc_url_raw((string) get_permalink(absint($settings['selected_product'])));
        }

        $raw_link = trim((string) ($settings['custom_link'] ?? ''));

        if ('' === $raw_link) {
            return '';
        }

        if ('#' === $raw_link[0]) {
            // Ancla local: solo caracteres seguros, si no se descarta.
            return preg_match('/^#[A-Za-z0-9\-_.:]*$/', $raw_link) ? $raw_link : '';
        }

        $sanitized_link = esc_url_raw($raw_link);

        // esc_url_raw() no añade protocolo a un dominio suelto ("ejemplo.com"),
        // que generaria un href relativo roto; y devuelve cadena vacia para
        // esquemas prohibidos (javascript:, data:). Ambos casos se descartan.
        return ('' !== $sanitized_link && preg_match('#^[a-z]+://#i', $sanitized_link)) ? $sanitized_link : '';
    }

    /**
     * Resuelve el contenido del popup segun el tipo configurado.
     *
     * Devuelve un array normalizado con las claves que consume la plantilla, o
     * null si no hay contenido mostrable. En el modo imagen los precios van
     * vacios: la plantilla omite los bloques sin datos.
     *
     * Claves de salida: id, title, description, price, sale_price,
     * regular_price, image, image_id, permalink, discount, button_text.
     */
    private function get_popup_content() {
        $settings = $this->get_popup_settings();
        $type = isset($settings['popup_type']) ? (string) $settings['popup_type'] : 'product';

        if ('product' !== $type) {
            return $this->get_custom_content($settings);
        }

        return $this->get_selected_product();
    }

    /**
     * Contenido del modo 'image': una imagen de la biblioteca mas, de forma
     * opcional, un boton con enlace (URL absoluta o ancla #id).
     *
     * El modo no escribe un titulo ni un texto propios: el unico texto visible
     * del popup es el titulo y la descripcion de la pestana General, compartidos
     * por todos los modos. Aqui 'title' se rellena solo con el texto alternativo
     * del adjunto (alt o nombre), que la plantilla usa como atributo alt.
     */
    private function get_custom_content($settings) {
        $image_id = isset($settings['custom_image_id']) ? absint($settings['custom_image_id']) : 0;

        if (0 === $image_id) {
            return null;
        }

        $title = trim((string) get_post_meta($image_id, '_wp_attachment_image_alt', true));

        if ('' === $title) {
            // Fallback al titulo del adjunto (WordPress lo deriva del nombre
            // del archivo la primera vez que se sube). Se usa SOLO como
            // atributo alt de la imagen; nunca se renderiza como nombre de
            // producto.
            $title = trim((string) get_the_title($image_id));
        }

        return array(
            'id' => 0,
            'title' => $title,
            'description' => '',
            'price' => '',
            'sale_price' => '',
            'regular_price' => '',
            'image' => wp_get_attachment_image_url($image_id, 'large'),
            'image_id' => $image_id,
            'permalink' => $this->get_popup_button_link($settings),
            'discount' => 0,
            'button_text' => trim((string) ($settings['button_text'] ?? '')),
        );
    }

    private function get_selected_product() {
        $settings = $this->get_popup_settings();
        
        if (empty($settings['selected_product'])) {
            return null;
        }
        
        $product_id = intval($settings['selected_product']);
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return null;
        }
        
        if ($this->is_product_excluded($product_id)) {
            return null;
        }
        
        return array(
            'id' => $product_id,
            'title' => $product->get_name(),
            'price' => $product->get_price(),
            'sale_price' => $product->get_sale_price(),
            'regular_price' => $product->get_regular_price(),
            'image' => wp_get_attachment_url($product->get_image_id()),
            'image_id' => absint($product->get_image_id()),
            'permalink' => get_permalink($product_id),
            'description' => $product->get_short_description(),
            'discount' => $this->calculate_discount($product),
            'button_text' => trim((string) ($settings['button_text'] ?? ''))
        );
    }
    
    private function calculate_discount($product) {
        if ($product->is_on_sale() && $product->get_regular_price() > 0) {
            $regular = floatval($product->get_regular_price());
            $sale = floatval($product->get_sale_price());
            if ($regular > 0) {
                return round((($regular - $sale) / $regular) * 100);
            }
        }
        return 0;
    }
    
    public function ajax_search_products() {
        // Verificar permisos de administrador
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        check_ajax_referer('ygb_admin_nonce', 'nonce');
        
        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        
        // MEJORA: Limitar búsqueda solo a productos publicados
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            's' => $search,
            'orderby' => 'title',
            'order' => 'ASC'
        );
        
        $products = get_posts($args);
        $results = array();
        
        foreach ($products as $product) {
            $wc_product = wc_get_product($product->ID);

            // wc_get_product() devuelve false si el ID ya no corresponde a un
            // producto válido (por ejemplo, borrado entre el get_posts y aquí).
            if (!$wc_product) {
                continue;
            }

            $results[] = array(
                'id' => $product->ID,
                'text' => $product->post_title . ' (#' . $product->ID . ')',
                'price' => wp_strip_all_tags(wc_price($wc_product->get_price())),
                'image' => wp_get_attachment_url($wc_product->get_image_id())
            );
        }
        
        wp_send_json_success($results);
    }
    
    public function ajax_save_tab() {
        // Verificar permisos de administrador
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permisos insuficientes');
        }
        
        check_ajax_referer('ygb_admin_nonce', 'nonce');
        
        $tab = isset($_POST['tab']) ? sanitize_text_field(wp_unslash($_POST['tab'])) : '';
        $data = isset($_POST['data']) && is_array($_POST['data']) ? wp_unslash($_POST['data']) : array();
        
        if (empty($tab) || empty($data)) {
            wp_send_json_error('Datos inválidos');
        }
        
        // MEJORA: Validar claves permitidas para evitar datos no deseados
        $allowed_keys = array('enabled', 'title', 'description', 'button_text', 'button_color', 'text_color', 
                              'background_color', 'overlay_color', 'animation', 'width', 'close_button', 
                              'selected_product', 'display_delay', 'show_on_exit', 'show_on_scroll', 
                              'scroll_percentage', 'show_always', 'show_close_after', 'cookie_expiration',
                              'mobile_disabled', 'tablet_disabled', 'popup_status', 'start_date', 'end_date',
                              'specific_pages_only', 'pages', 'popup_type', 'custom_image_id',
                              'custom_link');
        $data = array_intersect_key($data, array_flip($allowed_keys));
        
        $current_settings = get_option('ygb_ofertas_settings', array());
        
        // Sanitizar según la pestaña
        switch ($tab) {
            case 'general':
                $current_settings['enabled'] = isset($data['enabled']) && $data['enabled'] === '1' ? '1' : '0';
                $current_settings['title'] = sanitize_text_field($data['title'] ?? '¡Oferta Especial!');
                $current_settings['description'] = sanitize_textarea_field($data['description'] ?? '');
                $current_settings['button_text'] = sanitize_text_field($data['button_text'] ?? 'Ver Producto');
                break;
                
            case 'diseno':
                $current_settings['button_color'] = $this->sanitize_hex_color($data['button_color'] ?? '#007cba');
                $current_settings['text_color'] = $this->sanitize_hex_color($data['text_color'] ?? '#333333');
                $current_settings['background_color'] = $this->sanitize_hex_color($data['background_color'] ?? '#ffffff');
                $current_settings['overlay_color'] = $this->sanitize_rgba_color($data['overlay_color'] ?? 'rgba(0,0,0,0.7)');

                $animation = sanitize_key($data['animation'] ?? 'fade');
                $current_settings['animation'] = in_array($animation, ['fade', 'slide', 'zoom'], true) ? $animation : 'fade';

                $width = absint($data['width'] ?? 500);
                $current_settings['width'] = (string) max(200, min(1200, $width));
                $current_settings['close_button'] = isset($data['close_button']) && $data['close_button'] === '1' ? '1' : '0';
                break;
                
            case 'productos':
                // Tipo de popup: solo se aceptan los modos soportados (allowlist).
                $popup_type = isset($data['popup_type']) ? sanitize_key($data['popup_type']) : 'product';
                $current_settings['popup_type'] = in_array($popup_type, ['product', 'image'], true) ? $popup_type : 'product';

                if ('product' !== $current_settings['popup_type']) {
                    // Modo 'image': imagen de la biblioteca + boton opcional.
                    $image_id = absint($data['custom_image_id'] ?? 0);
                    if ($image_id > 0 && 'attachment' !== get_post_type($image_id)) {
                        $image_id = 0;
                    }
                    $current_settings['custom_image_id'] = (string) $image_id;

                    // El enlace del boton es opcional: se acepta una URL validada
                    // por esc_url_raw() o un ancla local (#id). El resto de
                    // valores (javascript:, dominios sueltos...) se descartan
                    // aqui y otra vez al renderizar.
                    $link = trim((string) ($data['custom_link'] ?? ''));
                    if ('' !== $link && '#' !== $link[0]) {
                        $link = esc_url_raw($link);
                    }
                    $current_settings['custom_link'] = sanitize_text_field($link);

                    // El modo imagen no depende del producto, pero se conserva el
                    // valor guardado por si el usuario vuelve al modo producto.
                    break;
                }

                $selected = sanitize_text_field($data['selected_product'] ?? '');
                
                // Verificar que el producto seleccionado no esté excluido
                if (!empty($selected) && $this->is_product_excluded(intval($selected))) {
                    wp_send_json_error('Este producto está excluido del popup. Por favor, selecciona otro producto o elimínalo de la lista de excluidos.');
                }
                
                $current_settings['selected_product'] = $selected;
                break;
                
            case 'comportamiento':
                $current_settings['display_delay'] = sanitize_text_field($data['display_delay'] ?? '5');
                $current_settings['show_on_exit'] = isset($data['show_on_exit']) && $data['show_on_exit'] === '1' ? '1' : '0';
                $current_settings['show_on_scroll'] = isset($data['show_on_scroll']) && $data['show_on_scroll'] === '1' ? '1' : '0';
                $current_settings['scroll_percentage'] = sanitize_text_field($data['scroll_percentage'] ?? '50');
                $current_settings['show_always'] = isset($data['show_always']) && $data['show_always'] === '1' ? '1' : '0';
                $current_settings['show_close_after'] = isset($data['show_close_after']) && $data['show_close_after'] === '1' ? '1' : '0';
                $current_settings['cookie_expiration'] = sanitize_text_field($data['cookie_expiration'] ?? '1');
                break;
                
            case 'programacion':
                $current_settings['mobile_disabled'] = isset($data['mobile_disabled']) && $data['mobile_disabled'] === '1' ? '1' : '0';
                $current_settings['tablet_disabled'] = isset($data['tablet_disabled']) && $data['tablet_disabled'] === '1' ? '1' : '0';

                $status = isset($data['popup_status']) ? sanitize_key($data['popup_status']) : 'immediate';
                $current_settings['popup_status'] = in_array($status, ['immediate', 'scheduled'], true) ? $status : 'immediate';

                $current_settings['start_date'] = $this->sanitize_datetime_local($data['start_date'] ?? '');
                $current_settings['end_date'] = $this->sanitize_datetime_local($data['end_date'] ?? '');
                $current_settings['specific_pages_only'] = isset($data['specific_pages_only']) && $data['specific_pages_only'] === '1' ? '1' : '0';

                $allowed_pages = ['all', 'home', 'shop', 'cart', 'checkout'];
                $pages = isset($data['pages']) && is_array($data['pages']) ? $data['pages'] : [];
                $pages = array_values(array_intersect(array_map('sanitize_key', $pages), $allowed_pages));
                $current_settings['pages'] = !empty($pages) ? $pages : ['all'];
                break;
        }
        
        update_option('ygb_ofertas_settings', $current_settings);
        
        wp_send_json_success(array(
            'message' => __('Configuración guardada correctamente', 'ygb-ofertas'),
            'settings' => $current_settings
        ));
    }
    
    private function sanitize_hex_color($color) {
        $color = trim((string) $color);
        if (preg_match('/^#([a-f0-9]{3}|[a-f0-9]{6})$/i', $color)) {
            return $color;
        }
        return '#007cba';
    }

    /**
     * Valida una cadena rgba()/rgb() antes de inyectarla en un atributo style.
     * esc_attr() escapa HTML pero no valida sintaxis CSS, por lo que un valor
     * como "red;} body{display:none" podría inyectar CSS. Aquí se rechaza.
     */
    private function sanitize_rgba_color($color) {
        $color = trim((string) $color);
        if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(?:,\s*(?:0|1|0?\.\d+)\s*)?\)$/i', $color)) {
            return $color;
        }
        return 'rgba(0,0,0,0.7)';
    }

    /**
     * Valida el formato datetime-local (Y-m-d\TH:i) que envía el navegador.
     * Devuelve cadena vacía si el valor no es válido.
     */
    private function sanitize_datetime_local($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $dt = \DateTime::createFromFormat('Y-m-d\TH:i', $value);
        return ($dt && $dt->format('Y-m-d\TH:i') === $value) ? $value : '';
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('YGB Ofertas', 'ygb-ofertas'),
            __('YGB Ofertas', 'ygb-ofertas'),
            'manage_options',
            'ygb-ofertas',
            array($this, 'render_admin_page'),
            'dashicons-megaphone',
            30
        );
        
        add_submenu_page(
            'ygb-ofertas',
            __('Configuración', 'ygb-ofertas'),
            __('Configuración', 'ygb-ofertas'),
            'manage_options',
            'ygb-ofertas',
            array($this, 'render_admin_page')
        );
        
        add_submenu_page(
            'ygb-ofertas',
            __('Productos Excluidos', 'ygb-ofertas'),
            __('Productos Excluidos', 'ygb-ofertas'),
            'manage_options',
            'ygb-ofertas-excluded',
            array($this, 'render_excluded_products_page')
        );
    }
    
    public function register_settings() {
        register_setting('ygb_ofertas_excluded_group', 'ygb_ofertas_excluded_products', array($this, 'sanitize_excluded_products'));
    }
    
    public function sanitize_excluded_products($input) {
        if (is_array($input)) {
            return array_map('intval', $input);
        }
        
        if (is_string($input) && !empty($input)) {
            $products = explode(',', $input);
            return array_map('intval', $products);
        }
        
        return array();
    }
    
    public function render_admin_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';

        $allowed_tabs = ['general', 'diseno', 'productos', 'comportamiento', 'programacion'];
        if (!in_array($active_tab, $allowed_tabs, true)) {
            $active_tab = 'general';
        }

        $settings = $this->get_popup_settings();
        
        $selected_product = null;
        if (!empty($settings['selected_product'])) {
            $product = wc_get_product(intval($settings['selected_product']));
            if ($product) {
                $selected_product = array(
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'price' => wp_strip_all_tags(wc_price($product->get_price())),
                    'image' => wp_get_attachment_url($product->get_image_id())
                );
            }
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Configuración YGB Ofertas', 'ygb-ofertas'); ?></h1>
            
            <div id="ygb-save-notice" class="notice" style="display:none;"></div>
            
            <?php
            $tabs = [
                'general'        => __('General', 'ygb-ofertas'),
                'diseno'         => __('Diseño', 'ygb-ofertas'),
                'productos'      => __('Producto', 'ygb-ofertas'),
                'comportamiento' => __('Comportamiento', 'ygb-ofertas'),
                'programacion'   => __('Programación', 'ygb-ofertas'),
            ];
            ?>
            <h2 class="nav-tab-wrapper">
                <?php foreach ($tabs as $tab_slug => $tab_label) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ygb-ofertas&tab=' . $tab_slug)); ?>"
                       class="nav-tab <?php echo $active_tab === $tab_slug ? 'nav-tab-active' : ''; ?>"
                       data-tab="<?php echo esc_attr($tab_slug); ?>">
                        <?php echo esc_html($tab_label); ?>
                    </a>
                <?php endforeach; ?>
            </h2>
            
            <div class="tab-content">
                <div id="tab-general" class="tab-pane" style="<?php echo $active_tab === 'general' ? 'display:block;' : 'display:none;'; ?>">
                    <?php $this->render_general_tab($settings); ?>
                </div>
                
                <div id="tab-diseno" class="tab-pane" style="<?php echo $active_tab === 'diseno' ? 'display:block;' : 'display:none;'; ?>">
                    <?php $this->render_diseno_tab($settings); ?>
                </div>
                
                <div id="tab-productos" class="tab-pane" style="<?php echo $active_tab === 'productos' ? 'display:block;' : 'display:none;'; ?>">
                    <?php $this->render_productos_tab($settings, $selected_product); ?>
                </div>
                
                <div id="tab-comportamiento" class="tab-pane" style="<?php echo $active_tab === 'comportamiento' ? 'display:block;' : 'display:none;'; ?>">
                    <?php $this->render_comportamiento_tab($settings); ?>
                </div>
                
                <div id="tab-programacion" class="tab-pane" style="<?php echo $active_tab === 'programacion' ? 'display:block;' : 'display:none;'; ?>">
                    <?php $this->render_programacion_tab($settings); ?>
                </div>
            </div>
            
            <div style="margin-top:20px; text-align:right;">
                <button type="button" id="ygb-save-tab" class="button button-primary" data-tab="<?php echo $active_tab; ?>">
                    <?php _e('Guardar cambios', 'ygb-ofertas'); ?>
                </button>
                <span id="ygb-save-spinner" style="display:none; margin-left:10px;">
                    <span class="spinner is-active" style="float:none; margin-top:0;"></span>
                </span>
            </div>
        </div>
        
        <style>
        .tab-content {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-top: none;
            margin-top: -1px;
        }
        .nav-tab-wrapper {
            border-bottom: 1px solid #ccd0d4;
        }
        .form-table th {
            width: 200px;
        }
        .ygb-preview {
            margin-top: 20px;
            padding: 20px;
            background: #f9f9f9;
            border: 1px dashed #ccc;
        }
        .product-selector-container {
            background: #f9f9f9;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            position: relative;
        }
        .selected-product-info {
            margin-top: 20px;
            padding: 15px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .selected-product-info img {
            max-width: 80px;
            max-height: 80px;
            object-fit: cover;
            border-radius: 4px;
        }
        .selected-product-details {
            flex: 1;
        }
        .selected-product-details h3 {
            margin: 0 0 10px 0;
        }
        .selected-product-details p {
            margin: 5px 0;
        }
        .remove-product {
            color: #dc3232;
            cursor: pointer;
            text-decoration: none;
        }
        .remove-product:hover {
            color: #a00;
        }
        #ygb-image-preview {
            margin-bottom: 15px;
        }
        #ygb-image-preview img {
            max-width: 260px;
            height: auto;
            max-height: none;
            object-fit: fill;
            border-radius: 4px;
            border: 1px solid #ddd;
            background: #fff;
            display: block;
        }
        .search-products {
            width: 100%;
            max-width: 400px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .product-search-results {
            position: absolute;
            max-width: 400px;
            width: 100%;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-top: none;
            background: #fff;
            display: none;
            z-index: 1000;
            box-shadow: 0 5px 10px rgba(0,0,0,0.1);
        }
        .product-search-result {
            padding: 10px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .product-search-result:hover {
            background: #f0f0f0;
        }
        .product-search-result img {
            max-width: 40px;
            max-height: 40px;
            object-fit: cover;
            border-radius: 4px;
        }
        .product-search-result .product-info {
            flex: 1;
        }
        .product-search-result .product-name {
            font-weight: bold;
            display: block;
        }
        .product-search-result .product-price {
            color: #46b450;
            font-size: 12px;
        }
        #ygb-save-notice {
            margin: 20px 0 0;
        }
        </style>
        <?php
    }
    
    private function render_general_tab($settings) {
        ?>
        <table class="form-table">
             <tr>
                <th scope="row"><?php _e('Estado del Popup', 'ygb-ofertas'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="enabled" value="1" <?php checked('1', $settings['enabled']); ?>>
                        <span style="color: #46b450; font-weight: bold;"><?php _e('Activar popup de ofertas', 'ygb-ofertas'); ?></span>
                    </label>
                    <p class="description">
                        <?php _e('Marca esta casilla para activar el popup en todo el sitio', 'ygb-ofertas'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Título del Popup', 'ygb-ofertas'); ?></th>
                <td>
                    <input type="text" name="title" value="<?php echo esc_attr($settings['title']); ?>" class="regular-text">
                    <p class="description"><?php _e('Título que aparecerá en el popup', 'ygb-ofertas'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Descripción', 'ygb-ofertas'); ?></th>
                <td>
                    <textarea name="description" rows="3" class="regular-text"><?php echo esc_textarea($settings['description']); ?></textarea>
                    <p class="description"><?php _e('Descripción adicional (opcional)', 'ygb-ofertas'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Texto del Botón', 'ygb-ofertas'); ?></th>
                <td>
                    <input type="text" name="button_text" value="<?php echo esc_attr($settings['button_text']); ?>" class="regular-text">
                </td>
            </tr>
        </table>
        <?php
    }
    
    private function render_diseno_tab($settings) {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Color del Botón', 'ygb-ofertas'); ?></th>
                <td>
                    <input type="color" name="button_color" value="<?php echo esc_attr($settings['button_color']); ?>">
                    <code><?php echo esc_attr($settings['button_color']); ?></code>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Color de Texto', 'ygb-ofertas'); ?></th>
                <td>
                    <input type="color" name="text_color" value="<?php echo esc_attr($settings['text_color']); ?>">
                    <code><?php echo esc_attr($settings['text_color']); ?></code>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Color de Fondo', 'ygb-ofertas'); ?></th>
                <td>
                    <input type="color" name="background_color" value="<?php echo esc_attr($settings['background_color']); ?>">
                    <code><?php echo esc_attr($settings['background_color']); ?></code>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Color del Overlay', 'ygb-ofertas'); ?></th>
                <td>
                    <input type="text" name="overlay_color" value="<?php echo esc_attr($settings['overlay_color']); ?>" class="regular-text">
                    <p class="description"><?php _e('Usa rgba() para transparencia: rgba(0,0,0,0.7)', 'ygb-ofertas'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Animación', 'ygb-ofertas'); ?></th>
                <td>
                    <select name="animation">
                        <option value="fade" <?php selected('fade', $settings['animation']); ?>><?php _e('Desvanecer', 'ygb-ofertas'); ?></option>
                        <option value="slide" <?php selected('slide', $settings['animation']); ?>><?php _e('Deslizar', 'ygb-ofertas'); ?></option>
                        <option value="zoom" <?php selected('zoom', $settings['animation']); ?>><?php _e('Zoom', 'ygb-ofertas'); ?></option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Ancho del Popup', 'ygb-ofertas'); ?></th>
                <td>
                    <input type="number" name="width" value="<?php echo esc_attr($settings['width']); ?>" min="200" max="1200"> px
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Botón de cerrar', 'ygb-ofertas'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="close_button" value="1" <?php checked('1', $settings['close_button']); ?>>
                        <?php _e('Mostrar botón de cerrar', 'ygb-ofertas'); ?>
                    </label>
                </td>
            </tr>
        </table>
        
        <div class="ygb-preview">
            <h3><?php _e('Vista previa', 'ygb-ofertas'); ?></h3>
            <p><?php _e('Los cambios se verán reflejados después de guardar.', 'ygb-ofertas'); ?></p>
        </div>
        <?php
    }
    
    private function render_productos_tab($settings, $selected_product = null) {
        $popup_type = isset($settings['popup_type']) ? (string) $settings['popup_type'] : 'product';
        if (!in_array($popup_type, ['product', 'image'], true)) {
            $popup_type = 'product';
        }

        $image_id  = absint($settings['custom_image_id'] ?? 0);
        $image_url = $image_id > 0 ? (string) wp_get_attachment_image_url($image_id, 'medium') : '';
        $image_alt = $image_id > 0 ? trim((string) get_post_meta($image_id, '_wp_attachment_image_alt', true)) : '';
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Tipo de contenido del popup', 'ygb-ofertas'); ?></th>
                <td>
                    <fieldset>
                        <label style="display:block; margin-bottom:6px;">
                            <input type="radio" name="popup_type" value="product" <?php checked('product', $popup_type); ?>>
                            <strong><?php esc_html_e('Producto de WooCommerce', 'ygb-ofertas'); ?></strong>
                            <span class="description"> &mdash; <?php esc_html_e('muestra la imagen, el precio y el boton con el enlace al producto.', 'ygb-ofertas'); ?></span>
                        </label>
                        <label style="display:block;">
                            <input type="radio" name="popup_type" value="image" <?php checked('image', $popup_type); ?>>
                            <strong><?php esc_html_e('Solo una imagen', 'ygb-ofertas'); ?></strong>
                            <span class="description"> &mdash; <?php esc_html_e('sin producto: muestra una imagen de la biblioteca y, opcionalmente, un boton con enlace.', 'ygb-ofertas'); ?></span>
                        </label>
                    </fieldset>
                    <p class="description">
                        <?php esc_html_e('Puedes alternar entre los dos tipos cuando quieras: la seleccion de producto y la imagen se conservan por separado.', 'ygb-ofertas'); ?>
                    </p>
                </td>
            </tr>

            <tr class="ygb-mode-product"<?php echo 'product' !== $popup_type ? ' style="display:none;"' : ''; ?>>
                <th scope="row"><?php esc_html_e('Producto a mostrar', 'ygb-ofertas'); ?></th>
                <td>
                    <div class="product-selector-container">
                        <input type="text"
                               id="product-search"
                               class="search-products"
                               placeholder="<?php esc_attr_e('Buscar producto por nombre o ID...', 'ygb-ofertas'); ?>"
                               autocomplete="off">

                        <div id="product-search-results" class="product-search-results"></div>

                        <div id="selected-product-info" class="selected-product-info"<?php echo $selected_product ? '' : ' style="display:none;"'; ?>>
                            <?php if ($selected_product) : ?>
                                <img src="<?php echo esc_url((string) $selected_product['image']); ?>" alt="">
                                <div class="selected-product-details">
                                    <h3><?php echo esc_html($selected_product['name']); ?></h3>
                                    <p><strong>ID:</strong> <?php echo esc_html((string) $selected_product['id']); ?></p>
                                    <p><strong><?php esc_html_e('Precio:', 'ygb-ofertas'); ?></strong> <?php echo wp_kses_post($selected_product['price']); ?></p>
                                </div>
                                <a href="#" id="remove-product" class="remove-product"><?php esc_html_e('Quitar producto', 'ygb-ofertas'); ?></a>
                            <?php endif; ?>
                        </div>

                        <input type="hidden" name="selected_product" id="selected_product" value="<?php echo esc_attr($settings['selected_product']); ?>">

                        <p class="description">
                            <?php esc_html_e('Selecciona el producto especifico que quieres mostrar en el popup.', 'ygb-ofertas'); ?>
                        </p>
                    </div>
                </td>
            </tr>

            <tr class="ygb-mode-media"<?php echo 'product' === $popup_type ? ' style="display:none;"' : ''; ?>>
                <th scope="row"><?php esc_html_e('Imagen del popup', 'ygb-ofertas'); ?></th>
                <td>
                    <div class="product-selector-container">
                        <div id="ygb-image-preview"<?php echo '' === $image_url ? ' style="display:none;"' : ''; ?>>
                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($image_alt); ?>">
                        </div>

                        <p>
                            <button type="button" class="button" id="ygb-select-image"><?php esc_html_e('Seleccionar imagen', 'ygb-ofertas'); ?></button>
                            <button type="button" class="button button-link-delete" id="ygb-remove-image"<?php echo $image_id > 0 ? '' : ' style="display:none;"'; ?>><?php esc_html_e('Quitar imagen', 'ygb-ofertas'); ?></button>
                        </p>

                        <input type="hidden" name="custom_image_id" id="custom_image_id" value="<?php echo esc_attr((string) $image_id); ?>">

                        <p class="description">
                            <?php esc_html_e('Elige una imagen ya existente en la biblioteca de medios: desde aqui no se sube ningun archivo.', 'ygb-ofertas'); ?>
                        </p>
                    </div>
                </td>
            </tr>

            <tr class="ygb-mode-media"<?php echo 'product' === $popup_type ? ' style="display:none;"' : ''; ?>>
                <th scope="row"><?php esc_html_e('Enlace del boton (opcional)', 'ygb-ofertas'); ?></th>
                <td>
                    <input type="text"
                           name="custom_link"
                           id="custom_link"
                           value="<?php echo esc_attr((string) ($settings['custom_link'] ?? '')); ?>"
                           class="large-text code"
                           placeholder="https://example.com/promocion  |  #seccion-de-la-pagina">
                    <p class="description">
                        <?php esc_html_e('URL absoluta o ancla local (#id). El texto del boton se define en la pestana General. Si lo dejas vacio, el popup se muestra sin boton.', 'ygb-ofertas'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    private function render_comportamiento_tab($settings) {
        ?>
        <table class="form-table">
             <tr>
                <th scope="row"><?php _e('Retraso en la visualización', 'ygb-ofertas'); ?></th>
                <td>
                    <input type="number" name="display_delay" value="<?php echo esc_attr($settings['display_delay']); ?>" min="0" max="300" step="0.5">
                    <span><?php _e('segundos', 'ygb-ofertas'); ?></span>
                    <p class="description">
                        <?php _e('Tiempo de espera antes de mostrar el popup (0 = inmediato)', 'ygb-ofertas'); ?>
                    </p>
                </td>
             </tr>
            
             <tr>
                <th scope="row"><?php _e('Mostrar siempre', 'ygb-ofertas'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="show_always" value="1" <?php checked('1', $settings['show_always']); ?>>
                        <?php _e('Mostrar en cada visita (ignorar cookies)', 'ygb-ofertas'); ?>
                    </label>
                </td>
             </tr>
            
             <tr>
                <th scope="row"><?php _e('Popup al salir', 'ygb-ofertas'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="show_on_exit" value="1" <?php checked('1', $settings['show_on_exit']); ?>>
                        <?php _e('Mostrar cuando el usuario intenta salir del sitio', 'ygb-ofertas'); ?>
                    </label>
                </td>
             </tr>
            
             <tr>
                <th scope="row"><?php _e('Popup al hacer scroll', 'ygb-ofertas'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="show_on_scroll" value="1" <?php checked('1', $settings['show_on_scroll']); ?>>
                        <?php _e('Mostrar al hacer scroll', 'ygb-ofertas'); ?>
                    </label>
                </td>
             </tr>
            
             <tr>
                <th scope="row"><?php _e('Porcentaje de scroll', 'ygb-ofertas'); ?></th>
                <td>
                    <input type="number" name="scroll_percentage" value="<?php echo esc_attr($settings['scroll_percentage']); ?>" min="0" max="100"> %
                </td>
             </tr>
            
             <tr>
                <th scope="row"><?php _e('Expiración de la cookie', 'ygb-ofertas'); ?></th>
                <td>
                    <input type="number" name="cookie_expiration" value="<?php echo esc_attr($settings['cookie_expiration']); ?>" min="1" max="365"> <?php _e('días', 'ygb-ofertas'); ?>
                </td>
             </tr>
            
             <tr>
                <th scope="row"><?php _e('Cerrar después', 'ygb-ofertas'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="show_close_after" value="1" <?php checked('1', $settings['show_close_after']); ?>>
                        <?php _e('Mostrar enlace para cerrar después del contenido', 'ygb-ofertas'); ?>
                    </label>
                </td>
             </tr>
         </table>
        <?php
    }
    
    private function render_programacion_tab($settings) {
        ?>
        <table class="form-table">
             <tr>
                <th scope="row"><?php _e('Dispositivos', 'ygb-ofertas'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="mobile_disabled" value="1" <?php checked('1', $settings['mobile_disabled']); ?>>
                        <?php _e('Desactivar en móviles', 'ygb-ofertas'); ?>
                    </label>
                    <br>
                    <label>
                        <input type="checkbox" name="tablet_disabled" value="1" <?php checked('1', $settings['tablet_disabled']); ?>>
                        <?php _e('Desactivar en tablets', 'ygb-ofertas'); ?>
                    </label>
                </td>
             </tr>
            
             <tr>
                <th scope="row"><?php _e('Programar popup', 'ygb-ofertas'); ?></th>
                <td>
                    <label>
                        <input type="radio" name="popup_status" value="immediate" <?php checked('immediate', $settings['popup_status']); ?>>
                        <?php _e('Mostrar inmediatamente (si está activado)', 'ygb-ofertas'); ?>
                    </label>
                    <br>
                    <label>
                        <input type="radio" name="popup_status" value="scheduled" <?php checked('scheduled', $settings['popup_status']); ?>>
                        <?php _e('Programar fechas', 'ygb-ofertas'); ?>
                    </label>
                    
                    <div id="fechas-programadas" style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-left: 4px solid #007cba; <?php echo $settings['popup_status'] === 'scheduled' ? 'display: block;' : 'display: none;'; ?>">
                        <p>
                            <label><?php _e('Fecha de inicio:', 'ygb-ofertas'); ?><br>
                                <input type="datetime-local" name="start_date" value="<?php echo esc_attr($settings['start_date']); ?>">
                            </label>
                        </p>
                        <p>
                            <label><?php _e('Fecha de fin:', 'ygb-ofertas'); ?><br>
                                <input type="datetime-local" name="end_date" value="<?php echo esc_attr($settings['end_date']); ?>">
                            </label>
                        </p>
                    </div>
                </td>
             </tr>
            
             <tr>
                <th scope="row"><?php _e('Páginas específicas', 'ygb-ofertas'); ?></th>
                <td>
                    <?php
                    $specific_pages_only = isset($settings['specific_pages_only']) ? (string) $settings['specific_pages_only'] : '0';
                    $settings_pages      = isset($settings['pages']) && is_array($settings['pages'])
                        ? array_map('sanitize_key', $settings['pages'])
                        : [];
                    ?>
                    <label>
                        <input type="checkbox" name="specific_pages_only" value="1" <?php checked('1', $specific_pages_only); ?>>
                        <?php _e('Mostrar solo en páginas seleccionadas', 'ygb-ofertas'); ?>
                    </label>
                    
                    <div id="selector-paginas" style="margin-top: 15px; <?php echo '1' === $specific_pages_only ? 'display: block;' : 'display: none;'; ?>">
                        <?php
                        $pages_list = [
                            'all'      => __('Todas las páginas', 'ygb-ofertas'),
                            'home'     => __('Página de inicio', 'ygb-ofertas'),
                            'shop'     => __('Tienda', 'ygb-ofertas'),
                            'cart'     => __('Carrito', 'ygb-ofertas'),
                            'checkout' => __('Finalizar compra', 'ygb-ofertas'),
                        ];
                        ?>
                        <select name="pages[]" multiple style="width: 100%; max-width: 400px; height: 150px;">
                            <?php foreach ($pages_list as $page_slug => $page_label) : ?>
                                <option value="<?php echo esc_attr($page_slug); ?>" <?php selected(in_array($page_slug, $settings_pages, true)); ?>>
                                    <?php echo esc_html($page_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Mantén presionada la tecla Ctrl para seleccionar múltiples páginas', 'ygb-ofertas'); ?>
                        </p>
                    </div>
                </td>
             </tr>
         </table>
        <?php
    }
    
    public function render_excluded_products_page() {
        $excluded = get_option('ygb_ofertas_excluded_products', array());
        if (!is_array($excluded)) {
            $excluded = array();
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Productos Excluidos del Popup', 'ygb-ofertas'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('ygb_ofertas_excluded_group'); ?>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                         <tr>
                            <th width="50"><?php _e('ID', 'ygb-ofertas'); ?></th>
                            <th><?php _e('Producto', 'ygb-ofertas'); ?></th>
                            <th><?php _e('SKU', 'ygb-ofertas'); ?></th>
                            <th><?php _e('Precio', 'ygb-ofertas'); ?></th>
                            <th width="100"><?php _e('Acción', 'ygb-ofertas'); ?></th>
                         </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($excluded)) {
                            foreach ($excluded as $product_id) {
                                $product = wc_get_product($product_id);
                                if ($product) {
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html((string) $product_id); ?></td>
                                        <td>
                                            <a href="<?php echo esc_url((string) get_edit_post_link($product_id)); ?>" target="_blank" rel="noopener noreferrer">
                                                <?php echo esc_html($product->get_name()); ?>
                                            </a>
                                        </td>
                                        <td><?php echo esc_html($product->get_sku()); ?></td>
                                        <td><?php echo wp_kses_post(wc_price($product->get_price())); ?></td>
                                        <td>
                                            <button type="button" class="button button-small remove-excluded" data-product-id="<?php echo esc_attr((string) $product_id); ?>">
                                                <?php esc_html_e('Quitar', 'ygb-ofertas'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            }
                        } else {
                            ?>
                            <tr>
                                <td colspan="5"><?php esc_html_e('No hay productos excluidos', 'ygb-ofertas'); ?></td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
                
                <input type="hidden" name="ygb_ofertas_excluded_products" id="ygb_ofertas_excluded_products" value="<?php echo esc_attr(implode(',', $excluded)); ?>">
                
                <?php submit_button(__('Guardar cambios', 'ygb-ofertas')); ?>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.remove-excluded').on('click', function() {
                if (!confirm('<?php echo esc_js(__('¿Estás seguro de que quieres quitar este producto?', 'ygb-ofertas')); ?>')) {
                    return;
                }
                
                var productId = $(this).data('product-id');
                var currentExcluded = $('#ygb_ofertas_excluded_products').val();
                var excludedArray = currentExcluded ? currentExcluded.split(',').filter(Boolean) : [];
                var newExcluded = excludedArray.filter(function(id) {
                    return id != productId;
                });
                $('#ygb_ofertas_excluded_products').val(newExcluded.join(','));
                $(this).closest('tr').fadeOut(400, function() {
                    $(this).remove();
                    if ($('tbody tr').length === 0) {
                        $('tbody').html('<tr><td colspan="5"><?php echo esc_js(__('No hay productos excluidos', 'ygb-ofertas')); ?></td></tr>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function add_product_metabox() {
        add_meta_box(
            'ygb_ofertas_product',
            __('YGB Ofertas', 'ygb-ofertas'),
            array($this, 'render_product_metabox'),
            'product',
            'side',
            'default'
        );
    }
    
    public function render_product_metabox($post) {
        wp_nonce_field('ygb_ofertas_product_metabox', 'ygb_ofertas_product_nonce');
        
        $excluded = get_option('ygb_ofertas_excluded_products', array());
        if (!is_array($excluded)) {
            $excluded = array();
        }
        // Comparación estricta sobre enteros, igual que is_product_excluded().
        $is_excluded = in_array((int) $post->ID, array_map('intval', $excluded), true);
        ?>
        <p>
            <label>
                <input type="checkbox" name="ygb_ofertas_exclude_product" value="1" <?php checked($is_excluded, true); ?>>
                <?php _e('Excluir este producto del popup', 'ygb-ofertas'); ?>
            </label>
        </p>
        <p class="description">
            <?php _e('Si marcas esta opción, este producto no aparecerá en los popups automáticos.', 'ygb-ofertas'); ?>
        </p>
        <?php
    }
    
    public function save_product_metabox($post_id) {
        // Verificar nonce
        if (!isset($_POST['ygb_ofertas_product_nonce']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ygb_ofertas_product_nonce'])), 'ygb_ofertas_product_metabox')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $excluded = get_option('ygb_ofertas_excluded_products', array());
        if (!is_array($excluded)) {
            $excluded = array();
        }
        
        $excluded   = array_map('intval', $excluded);
        $product_id = (int) $post_id;

        if (isset($_POST['ygb_ofertas_exclude_product'])) {
            if (!in_array($product_id, $excluded, true)) {
                $excluded[] = $product_id;
            }
        } else {
            $excluded = array_diff($excluded, array($product_id));
        }
        
        update_option('ygb_ofertas_excluded_products', array_values(array_unique($excluded)));
    }
    
    public function save_popup_stats() {
        // Nonce obligatorio: si falla, muere con -1 (comportamiento estándar de WP en AJAX).
        check_ajax_referer('ygb_ofertas_nonce', 'nonce');

        // Endpoint público (lo consume un visitante anónimo), por lo que NO se
        // exige capability. La protección real es el nonce + la allowlist de acciones.
        $action = isset($_POST['stats_action'])
            ? sanitize_key(wp_unslash($_POST['stats_action']))
            : '';

        $allowed_actions = ['view', 'close', 'click'];
        if (!in_array($action, $allowed_actions, true)) {
            wp_send_json_error('Acción no válida', 400);
        }

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        if ($product_id <= 0) {
            wp_send_json_error('Producto no válido', 400);
        }

        /**
         * Punto de extensión para persistir estadísticas.
         * Ejemplo con APIs de WordPress (nunca SQL directo aquí):
         *   $views = (int) get_post_meta($product_id, '_ygb_views', true);
         *   update_post_meta($product_id, '_ygb_views', $views + 1);
         */
        do_action('ygb_ofertas_popup_stat', $action, $product_id);

        wp_send_json_success();
    }
}

/**
 * Aviso de administración cuando WooCommerce no está activo.
 */
function ygb_ofertas_missing_woocommerce_notice() {
    if (!current_user_can('activate_plugins')) {
        return;
    }
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e('YGB Ofertas requiere WooCommerce instalado y activado.', 'ygb-ofertas'); ?></p>
    </div>
    <?php
}

/**
 * Arranca el plugin.
 *
 * Se engancha a 'init' (prioridad 0) y no a 'plugins_loaded' porque
 * WooCommerce define su clase principal dentro de su propio 'plugins_loaded'
 * y el orden de carga entre plugins no está garantizado: comprobar
 * class_exists('WooCommerce') en 'plugins_loaded' puede dar false aunque
 * WooCommerce esté activo.
 */
function ygb_ofertas_init() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'ygb_ofertas_missing_woocommerce_notice');
        return;
    }

    return YGB_Ofertas::get_instance();
}
add_action('init', 'ygb_ofertas_init', 0);