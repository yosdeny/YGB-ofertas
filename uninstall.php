<?php
/**
 * Uninstall del plugin "YGB Ofertas".
 *
 * WordPress ejecuta este archivo CUANDO EL USUARIO PULSA "ELIMINAR" EN
 * wp-admin -> Plugins. En ese momento los archivos del plugin ya estan
 * borrados por el core, asi que aqui solo se limpian los datos que deja
 * en la base de datos.
 *
 * LISTA BLANCA EXPLICIT (no hay NINGUN barrido por prefijo):
 * Este script NO usa LIKE 'ygb_%' ni LIKE '_ygb_%'. Se podria comer claves
 * de otros plugins tuyos que compartan el prefijo "ygb_" (ygb-animal, etc.),
 * y eso es inaceptable. Aqui solo se borra una lista EXPLICITA de claves,
 * copiada literalmente del codigo de ygb-ofertas.php:
 *
 *   Opciones:            ygb_ofertas_settings
 *                        ygb_ofertas_excluded_products
 *   Meta de productos:   _ygb_views
 *
 * NOTA sobre '_ygb_views': hoy el plugin NO lo escribe (solo lo documenta en
 * un comentario como hook para integraciones). Se mantiene en la lista porque
 * es una clave EXACTA propia, no un patron. Si prefieres no borrar esa meta
 * (por si la usa otra integracion tuya), elimina la linea correspondiente de
 * $ygb_ofertas_uninstall_post_meta.
 *
 * Cualquier otra clave (las de ygb-animal o cualquier otro plugin) queda
 * INTACTA porque no aparece en esa lista y no hay patron comodin.
 *
 * Tampoco toca el filesystem, ni roles, ni capacidades, ni transients:
 * este plugin no crea nada de eso.
 *
 * @package YGB_Ofertas
 */

// Si alguien intenta abrir esto directamente en el navegador, fuera.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Claves EXACTAS de opciones que creo este plugin. Nada con comodines.
 */
$ygb_ofertas_uninstall_options = array(
	'ygb_ofertas_settings',
	'ygb_ofertas_excluded_products',
);

/**
 * Claves EXACTAS de post meta que usa este plugin.
 */
$ygb_ofertas_uninstall_post_meta = array(
	'_ygb_views',
);

if ( is_multisite() ) {
	// En multisite las opciones son por sitio: hay que recorrerlos todos.
	$ygb_ofertas_uninstall_sites = get_sites(
		array(
			'number'     => 0,
			'fields'     => 'ids',
			'network_id' => get_current_network_id(),
		)
	);

	foreach ( $ygb_ofertas_uninstall_sites as $ygb_ofertas_uninstall_site_id ) {
		switch_to_blog( (int) $ygb_ofertas_uninstall_site_id );
		ygb_ofertas_uninstall_cleanup( $ygb_ofertas_uninstall_options, $ygb_ofertas_uninstall_post_meta );
		restore_current_blog();
	}

	// Por si algo se guardo a nivel de red con update_site_option().
	delete_site_option( 'ygb_ofertas_settings' );
	delete_site_option( 'ygb_ofertas_excluded_products' );
} else {
	ygb_ofertas_uninstall_cleanup( $ygb_ofertas_uninstall_options, $ygb_ofertas_uninstall_post_meta );
}

/**
 * Borra SOLO las claves exactas listadas arriba.
 *
 * delete_option() y delete_post_meta_by_key() comparan la clave por valor
 * exacto (=), nunca con LIKE, asi que es imposible que alcancen a claves
 * de otros plugins como 'ygb_animal_*' o '_ygb_animal_views'.
 *
 * @param string[] $options   Claves de wp_options a borrar (exactas).
 * @param string[] $post_meta Claves de wp_postmeta a borrar (exactas).
 */
function ygb_ofertas_uninstall_cleanup( $options, $post_meta ) {
	global $wpdb;

	// 1) Opciones del plugin.
	foreach ( (array) $options as $option_key ) {
		delete_option( $option_key );
	}

	// 2) Meta de productos/posts del plugin.
	foreach ( (array) $post_meta as $meta_key ) {
		delete_post_meta_by_key( $meta_key );
	}

	// 3) Red de seguridad: filas sueltas con ESTAS MISMAS claves exactas
	//    en otras tablas de meta. Comodines: CERO.
	$all_keys = array_unique( array_merge( (array) $options, (array) $post_meta ) );

	foreach ( $all_keys as $meta_key ) {
		$wpdb->delete( $wpdb->commentmeta, array( 'meta_key' => $meta_key ) );
		$wpdb->delete( $wpdb->termmeta, array( 'meta_key' => $meta_key ) );
		$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => $meta_key ) );
		$wpdb->delete( $wpdb->sitemeta, array( 'meta_key' => $meta_key ) );
	}
}
