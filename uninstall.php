<?php
/**
 * Uninstall handler de YGB Ofertas.
 *
 * WordPress ejecuta automáticamente este archivo cuando el usuario pulsa
 * "Eliminar" en la lista de plugins (delete_plugins() de
 * wp-admin/includes/plugin.php). Se borra TODO lo que el plugin crea:
 *
 *   1. Opciones de la tabla {prefix}_options (configuración y productos
 *      excluidos) y cualquier opción/transient con prefijo del plugin.
 *   2. Transients y sus timeouts (_transient_ygb_*, _transient_timeout_ygb_*).
 *   3. Metadata creada por el plugin: post meta, comment meta, user meta,
 *      term meta y blog meta (esta última solo en multisite).
 *   4. Roles y capacidades propios del plugin (los roles del core NO se tocan,
 *      solo se les retiran las capacidades 'ygb_*').
 *   5. Archivos y carpetas generados dentro de wp-content/uploads.
 *   6. En multisite se limpia cada sitio de la red y también las opciones
 *      de red (sitemeta).
 *
 * Las cookies de navegador ('ygb_ofertas_shown') viven solo en el equipo del
 * visitante, por lo que no es posible borrarlas desde el servidor; se elimina
 * su fuente (los ajustes que las generan).
 *
 * @package YGB_Ofertas
 * @since   1.8.2
 */

// Bloquear el acceso directo por URL: WP_UNINSTALL_PLUGIN solo lo define WordPress.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Slug del plugin (carpeta/archivo principal). Debe coincidir con el real.
 */
define('YGB_OFERTAS_UNINSTALL_BASENAME', 'ygb-ofertas/ygb-ofertas.php');

/**
 * Claves de opción creadas explícitamente por el plugin.
 *
 * @var string[]
 */
$ygb_ofertas_options = array(
    'ygb_ofertas_settings',          // Configuración general del popup.
    'ygb_ofertas_excluded_products', // Productos excluidos (metabox y pestaña).
);

/**
 * Prefijos de clave usados por el plugin para metadata y transients.
 * Cualquier clave que empiece por alguno de ellos es propiedad del plugin.
 *
 * @var string[]
 */
$ygb_ofertas_meta_prefixes = array('_ygb_', 'ygb_');

/**
 * Carpetas (dentro de wp-content/uploads) que el plugin pudo haber creado.
 *
 * @var string[]
 */
$ygb_ofertas_upload_dirs = array('ygb-ofertas', 'ygb_ofertas');

/**
 * Devuelve los nombres de opción de la tabla de opciones cuyo nombre coincide
 * con un patrón LIKE.
 *
 * @param string $like Patrón LIKE (por ejemplo 'ygb\_%').
 * @return string[]
 */
function ygb_ofertas_get_option_names_like($like) {
    global $wpdb;

    $names = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $like
        )
    );

    return is_array($names) ? $names : array();
}

/**
 * Borra recursivamente un directorio y todo su contenido.
 *
 * Solo se invoca sobre rutas construidas dentro de wp-content/uploads.
 *
 * @param string $dir Ruta absoluta del directorio.
 * @return void
 */
function ygb_ofertas_rrmdir($dir) {
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if (!is_array($items)) {
        return;
    }

    foreach ($items as $item) {
        if ('.' === $item || '..' === $item) {
            continue;
        }

        $path = trailingslashit($dir) . $item;

        if (is_dir($path) && !is_link($path)) {
            ygb_ofertas_rrmdir($path);
        } elseif (is_file($path) || is_link($path)) {
            @unlink($path);
        }
    }

    @rmdir($dir);
}

/**
 * Devuelve la tabla de metadata correspondiente al tipo indicado, o null si
 * la tabla no existe en esta instalación.
 *
 * @param string $meta_type 'post' | 'comment' | 'term' | 'user' | 'blog'.
 * @return string|null
 */
function ygb_ofertas_meta_table($meta_type) {
    global $wpdb;

    switch ($meta_type) {
        case 'post':
            return $wpdb->postmeta;
        case 'comment':
            return $wpdb->commentmeta;
        case 'term':
            return $wpdb->termmeta;
        case 'user':
            return $wpdb->usermeta;
        case 'blog':
            return (is_multisite() && !empty($wpdb->blogmeta)) ? $wpdb->blogmeta : null;
    }

    return null;
}

/**
 * Elimina toda la metadata cuyas claves empiezan por alguno de los prefijos
 * del plugin, usando delete_metadata_by_mid() (API de WordPress) en lugar de
 * SQL directo en el borrado.
 *
 * Nota: se recorren las filas por meta_id porque es la única forma de borrar
 * claves del plugin cuyo nombre exacto no se conoce (por ejemplo, las
 * estadísticas registradas a través del hook 'ygb_ofertas_popup_stat').
 *
 * @param string   $meta_type 'post' | 'comment' | 'term' | 'user' | 'blog'.
 * @param string[] $prefixes  Prefijos de clave a eliminar.
 * @return int Número de filas eliminadas.
 */
function ygb_ofertas_delete_metadata_by_prefix($meta_type, $prefixes) {
    global $wpdb;

    $table = ygb_ofertas_meta_table($meta_type);
    if (null === $table) {
        return 0;
    }

    $ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT meta_id FROM {$table} WHERE meta_key LIKE %s OR meta_key LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->esc_like($prefixes[0]) . '%',
            $wpdb->esc_like($prefixes[1]) . '%'
        )
    );

    if (empty($ids)) {
        return 0;
    }

    $deleted = 0;
    foreach ($ids as $meta_id) {
        if (delete_metadata_by_mid($meta_type, (int) $meta_id)) {
            ++$deleted;
        }
    }

    return $deleted;
}

/**
 * Elimina los roles creados por el plugin y retira las capacidades 'ygb_*'
 * de los roles restantes (incluidos los del core, que nunca se borran).
 *
 * @return void
 */
function ygb_ofertas_remove_roles_and_caps() {
    $roles = get_option('user_roles');
    if (!is_array($roles)) {
        return;
    }

    // 1) Roles propios del plugin.
    foreach (array_keys($roles) as $role_key) {
        if (0 === strpos((string) $role_key, 'ygb_')) {
            remove_role($role_key);
        }
    }

    // 2) Capacidades 'ygb_*' en el resto de roles.
    $remaining = get_option('user_roles');
    if (!is_array($remaining)) {
        return;
    }

    foreach (array_keys($remaining) as $role_key) {
        $role = get_role((string) $role_key);
        if (!$role instanceof WP_Role) {
            continue;
        }

        foreach (array_keys((array) $role->capabilities) as $cap) {
            if (0 === strpos((string) $cap, 'ygb_')) {
                $role->remove_cap((string) $cap);
            }
        }
    }
}

/**
 * Limpia todos los datos del plugin correspondientes a UN sitio.
 *
 * @param string[] $option_names    Claves de opción concretas a borrar.
 * @param string[] $meta_prefixes   Prefijos de metadata a borrar.
 * @param string[] $upload_dirs     Carpetas a borrar dentro de uploads.
 * @return void
 */
function ygb_ofertas_purge_site_data($option_names, $meta_prefixes, $upload_dirs) {
    // -------------------------------------------------------------------------
    // 1) Opciones concretas del plugin.
    // -------------------------------------------------------------------------
    foreach ($option_names as $option_name) {
        delete_option($option_name);
    }

    // -------------------------------------------------------------------------
    // 2) Transients y timeouts del plugin.
    //    delete_transient() ya elimina '_transient_X' y '_transient_timeout_X'.
    // -------------------------------------------------------------------------
    foreach ($option_names as $option_name) {
        delete_transient($option_name);
    }

    // Cualquier opción o transient cuyo nombre empiece por los prefijos.
    // El guion bajo es un comodín de LIKE, así que se escapa con esc_like()
    // para que solo coincida con el literal 'ygb_' / '_ygb_'.
    foreach ($meta_prefixes as $prefix) {
        global $wpdb;

        foreach (ygb_ofertas_get_option_names_like($wpdb->esc_like($prefix) . '%') as $name) {
            delete_option($name);
        }
    }

    // -------------------------------------------------------------------------
    // 3) Metadata (post, comentario, término, usuario y, en multisite, blog).
    // -------------------------------------------------------------------------
    ygb_ofertas_delete_metadata_by_prefix('post', $meta_prefixes);
    ygb_ofertas_delete_metadata_by_prefix('comment', $meta_prefixes);
    ygb_ofertas_delete_metadata_by_prefix('term', $meta_prefixes);
    ygb_ofertas_delete_metadata_by_prefix('user', $meta_prefixes);
    ygb_ofertas_delete_metadata_by_prefix('blog', $meta_prefixes);

    // -------------------------------------------------------------------------
    // 4) Roles y capacidades.
    // -------------------------------------------------------------------------
    ygb_ofertas_remove_roles_and_caps();

    // -------------------------------------------------------------------------
    // 5) Archivos generados en wp-content/uploads.
    // -------------------------------------------------------------------------
    $uploads = wp_get_upload_dir();
    if (!empty($uploads['basedir']) && empty($uploads['error'])) {
        foreach ($upload_dirs as $folder) {
            $path = trailingslashit($uploads['basedir']) . $folder;
            if (is_dir($path)) {
                ygb_ofertas_rrmdir($path);
            }
        }
    }
}

/*
 * ====================================================================================
 * Ejecución
 * ====================================================================================
 */

// Seguridad extra: si WordPress nos pasa qué plugin se está borrando y no es
// el nuestro, no hacemos nada.
if (isset($_GET['plugin']) && sanitize_text_field(wp_unslash($_GET['plugin'])) !== YGB_OFERTAS_UNINSTALL_BASENAME) {
    return;
}

if (is_multisite()) {
    // Multisite: limpiar uno por uno todos los sitios de la red.
    $ygb_ofertas_site_ids = get_sites(array('fields' => 'ids', 'number' => 100000));

    foreach ($ygb_ofertas_site_ids as $ygb_ofertas_site_id) {
        switch_to_blog((int) $ygb_ofertas_site_id);

        ygb_ofertas_purge_site_data(
            $ygb_ofertas_options,
            $ygb_ofertas_meta_prefixes,
            $ygb_ofertas_upload_dirs
        );

        restore_current_blog();
    }

    // Opciones de red (tabla sitemeta) creadas por el plugin.
    foreach ($ygb_ofertas_options as $ygb_ofertas_option) {
        delete_site_option($ygb_ofertas_option);
    }

    $ygb_ofertas_network_names = $GLOBALS['wpdb']->get_col(
        $GLOBALS['wpdb']->prepare(
            "SELECT meta_key FROM {$GLOBALS['wpdb']->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $GLOBALS['wpdb']->esc_like('ygb_') . '%',
            $GLOBALS['wpdb']->esc_like('_ygb_') . '%'
        )
    );

    if (is_array($ygb_ofertas_network_names)) {
        foreach ($ygb_ofertas_network_names as $ygb_ofertas_network_name) {
            delete_site_option($ygb_ofertas_network_name);
        }
    }
} else {
    // Instalación individual.
    ygb_ofertas_purge_site_data(
        $ygb_ofertas_options,
        $ygb_ofertas_meta_prefixes,
        $ygb_ofertas_upload_dirs
    );
}
