<?php
/**
 * Plugin Name: Stad Wageningen Doorplaatser
 * Plugin URI:  https://www.stadwageningen.nl/tip-de-redactie
 * Description: Zet een WordPress bericht klaar en plaats het automatisch door naar stadwageningen.nl via een Tampermonkey-userscript (browser-side, geen server-side login, volledig automatisch).
 * Version:     3.0.1
 * Author:      Redactie
 * Text Domain: stadwag-doorplaatsen
 * License:     GPL-2.0+
 */

defined( 'ABSPATH' ) || exit;

define( 'STADWAG_VERSION',     '3.0.1' );
define( 'STADWAG_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'STADWAG_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'STADWAG_TARGET_BASE', 'https://www.stadwageningen.nl' );
define( 'STADWAG_FORM_PATH',   '/tip-de-redactie' );

// Let op: includes/class-stadwag-api.php (server-side login/submit) is sinds
// v2.0.0 niet meer in gebruik. Stad Wageningen blokkeerde server-side
// verzoeken; we plaatsen nu door via de browser (bookmarklet). Het bestand
// blijft staan als referentie maar wordt niet meer geladen.

require_once STADWAG_PLUGIN_DIR . 'includes/class-stadwag-rest.php';
require_once STADWAG_PLUGIN_DIR . 'includes/class-stadwag-admin.php';

new Stadwag_Rest(); // REST-endpoint moet ook buiten admin beschikbaar zijn

if ( is_admin() ) {
    new Stadwag_Admin();
}
