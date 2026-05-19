<?php
/**
 * Plugin Name: Stad Wageningen Doorplaatser
 * Plugin URI:  https://www.stadwageningen.nl/tip-de-redactie
 * Description: Plaatst een WordPress bericht door naar stadwageningen.nl via de knop "Tip de redactie".
 * Version:     1.1.2
 * Author:      Redactie
 * Text Domain: stadwag-doorplaatsen
 * License:     GPL-2.0+
 */

defined( 'ABSPATH' ) || exit;

define( 'STADWAG_VERSION',     '1.1.2' );
define( 'STADWAG_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'STADWAG_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'STADWAG_TARGET_BASE', 'https://www.stadwageningen.nl' );
define( 'STADWAG_FORM_PATH',   '/tip-de-redactie' );
define( 'STADWAG_LOGIN_PATH',  '/login' );

require_once STADWAG_PLUGIN_DIR . 'includes/class-stadwag-api.php';
require_once STADWAG_PLUGIN_DIR . 'includes/class-stadwag-admin.php';

if ( is_admin() ) {
    new Stadwag_Admin();
}
