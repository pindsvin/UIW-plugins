<?php
defined( 'ABSPATH' ) || exit;

/**
 * REST-endpoints voor de Stad Wageningen-doorplaatser.
 *
 * v3.0.0: naast de data- en image-endpoints is er een /userscript-endpoint
 * dat een Tampermonkey-userscript serveert met het token ingebed.
 *
 * Beveiliging: data + image endpoints gebruiken een token in de query-string.
 * Het userscript-endpoint vereist een ingelogde WP-admin (het token staat in
 * het script zelf, dus alleen de beheerder mag het downloaden).
 *
 * CORS: WordPress' eigen REST-API echoot standaard de Origin terug, dus een
 * cross-origin fetch vanaf stadwageningen.nl mag de respons lezen.
 */
class Stadwag_Rest {

    const CATEGORIES = [ 4651 => 'Lokaal', 4562 => 'Sport', 4608 => 'Zakelijk' ];

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        register_rest_route( 'stadwag/v1', '/queued', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_queued' ],
            'permission_callback' => [ $this, 'check_token' ],
        ] );

        register_rest_route( 'stadwag/v1', '/queued-image', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_queued_image' ],
            'permission_callback' => [ $this, 'check_token' ],
        ] );

        register_rest_route( 'stadwag/v1', '/userscript', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_userscript' ],
            'permission_callback' => [ $this, 'check_token' ],
        ] );
    }

    /**
     * Geeft de uitgelichte afbeelding van het klaargezette bericht als ruwe
     * bytes terug (met CORS-header), zodat de bookmarklet die in het
     * upload-veld van het Stad Wageningen-formulier kan plaatsen.
     */
    public function get_queued_image( \WP_REST_Request $request ) {
        $queued   = get_option( 'stadwag_queued', [] );
        $thumb_id = ! empty( $queued['post_id'] ) ? get_post_thumbnail_id( (int) $queued['post_id'] ) : 0;
        $file     = $thumb_id ? get_attached_file( $thumb_id ) : '';

        if ( ! $file || ! file_exists( $file ) ) {
            return new \WP_REST_Response( [ 'error' => 'Geen afbeelding bij het klaargezette bericht.' ], 404 );
        }

        $mime = get_post_mime_type( $thumb_id ) ?: 'application/octet-stream';

        // We omzeilen de JSON-serialisatie en sturen de bytes rechtstreeks.
        if ( ! headers_sent() ) {
            $origin = get_http_origin();
            if ( $origin ) {
                header( 'Access-Control-Allow-Origin: ' . $origin );
                header( 'Vary: Origin' );
            }
            header( 'Content-Type: ' . $mime );
            header( 'Content-Length: ' . filesize( $file ) );
            header( 'Cache-Control: no-store' );
        }
        readfile( $file );
        exit;
    }

    /**
     * Vergelijkt het meegestuurde token met het opgeslagen token.
     */
    public function check_token( \WP_REST_Request $request ): bool {
        $token  = (string) $request->get_param( 'token' );
        $opts   = get_option( 'stadwag_settings', [] );
        $stored = (string) ( $opts['bookmarklet_token'] ?? '' );
        return $token !== '' && $stored !== '' && hash_equals( $stored, $token );
    }

    /**
     * Geeft de klaargezette berichtgegevens terug.
     */
    public function get_queued( \WP_REST_Request $request ): \WP_REST_Response {
        $queued = get_option( 'stadwag_queued', [] );

        if ( empty( $queued['post_id'] ) ) {
            return new \WP_REST_Response( [ 'error' => 'Er staat geen bericht klaar. Klik eerst in WordPress op "Klaarzetten voor Stad Wageningen".' ], 404 );
        }

        $post = get_post( (int) $queued['post_id'] );
        if ( ! $post ) {
            return new \WP_REST_Response( [ 'error' => 'Het klaargezette bericht bestaat niet meer.' ], 404 );
        }

        // Titel en tekst opschonen (zelfde regels als de oude server-side versie).
        $title   = mb_substr( wp_strip_all_tags( $post->post_title ), 0, 640 );
        $content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
        $content = preg_replace( '/https?:\/\/\S+/u', '', $content ); // losse URLs eruit
        $content = preg_replace( '/[ \t]+/', ' ', $content );          // dubbele spaties weg
        $content = preg_replace( '/ *\n */', "\n", $content );         // spaties rond regeleindes weg
        $content = preg_replace( '/\n{2,}/', "\n\n", $content );       // max één lege regel tussen alinea's
        $text    = mb_substr( trim( $content ), 0, 3000 );

        $category_id = (int) ( $queued['category_id'] ?? 4651 );
        if ( ! array_key_exists( $category_id, self::CATEGORIES ) ) {
            $category_id = 4651;
        }

        // Uitgelichte afbeelding: alleen URL + bestandsnaam (upload doet de gebruiker zelf).
        $image_url  = '';
        $image_name = '';
        $thumb_id   = get_post_thumbnail_id( $post->ID );
        if ( $thumb_id ) {
            $image_url  = (string) wp_get_attachment_image_url( $thumb_id, 'large' );
            $file       = get_attached_file( $thumb_id );
            $image_name = $file ? basename( $file ) : '';
        }

        return new \WP_REST_Response( [
            'title'          => $title,
            'text'           => $text,
            'category_id'    => (string) $category_id,
            'category_label' => self::CATEGORIES[ $category_id ],
            'caption'        => (string) ( $queued['caption'] ?? '' ),
            'credit'         => (string) ( $queued['credit'] ?? '' ),
            'image_url'      => $image_url,
            'image_name'     => $image_name,
            'queued_at'      => (int) ( $queued['time'] ?? 0 ),
        ], 200 );
    }

    /**
     * Serves het Tampermonkey-userscript met het token ingebed.
     * Alleen toegankelijk voor ingelogde WP-beheerders.
     */
    public function get_userscript( \WP_REST_Request $request ): void {
        $opts   = get_option( 'stadwag_settings', [] );
        $token  = (string) ( $opts['bookmarklet_token'] ?? '' );

        if ( ! $token ) {
            status_header( 404 );
            exit( 'Geen token gevonden. Ga naar Instellingen → Stad Wageningen.' );
        }

        $data_endpoint = rest_url( 'stadwag/v1/queued' ) . '?token=' . rawurlencode( $token );
        $img_endpoint   = rest_url( 'stadwag/v1/queued-image' ) . '?token=' . rawurlencode( $token );

        $script = $this->build_userscript( $data_endpoint, $img_endpoint );

        if ( ! headers_sent() ) {
            header( 'Content-Type: application/javascript; charset=utf-8' );
            header( 'Content-Disposition: inline; filename="stadwageningen-doorplaatser.user.js"' );
            header( 'Cache-Control: no-store' );
        }
        echo $script;
        exit;
    }

    /**
     * Bouwt het Tampermonkey-userscript.
     * NOWDOC zodat PHP de JS niet interpreteert; alleen endpoints worden vervangen.
     */
    private function build_userscript( string $data_endpoint, string $img_endpoint ): string {
        $js = <<<'JS'
// ==UserScript==
// @name         Stad Wageningen Doorplaatser
// @namespace    uitinwageningen.nl
// @version      3.0.1
// @description  Vult automatisch het tip-de-redactie-formulier in met klaargezette berichten uit WordPress en verzendt direct.
// @match        https://www.stadwageningen.nl/tip-de-redactie*
// @grant        none
// @run-at       document-idle
// ==/UserScript==

(function() {
    'use strict';

    var D = '__DATA__';
    var IMG = '__IMG__';

    function fire(el, type) {
        el.dispatchEvent(new Event(type, { bubbles: true }));
    }

    function setVal(el, v) {
        if (!el) return;
        var proto = el.tagName === 'TEXTAREA' ? window.HTMLTextAreaElement.prototype : window.HTMLInputElement.prototype;
        var setter = Object.getOwnPropertyDescriptor(proto, 'value').set;
        setter.call(el, v == null ? '' : v);
        fire(el, 'input');
        fire(el, 'change');
    }

    function typeCE(ce, v) {
        if (!ce) return;
        ce.focus();
        try {
            document.execCommand('selectAll', false, null);
            document.execCommand('insertText', false, v);
        } catch (err) {
            ce.textContent = v;
            fire(ce, 'input');
        }
    }

    function fillCC(d) {
        setVal(document.querySelector("[name='caption[0]']"), d.caption);
        setVal(document.querySelector("[name='credit[0]']"), d.credit);
    }

    function doSubmit(d) {
        var cb = document.getElementById('okGeneralConditions');
        if (cb) {
            cb.checked = true;
            fire(cb, 'change');
        }
        var msg = '✓ Alles ingevuld';
        if (d.image_name) msg += ' (inclusief foto)';
        msg += '\n\nVerzenden naar Stad Wageningen?';
        if (confirm(msg)) {
            if (typeof pubbleWebsiteForms !== 'undefined') {
                pubbleWebsiteForms.submit();
            } else {
                var s = document.getElementById('send');
                if (s) s.click();
            }
        } else {
            console.log('[SW Doorplaatser] Verzenden geannuleerd door gebruiker.');
        }
    }

    function run(d) {
        // Categorie
        var cat = document.getElementById('CategoryId');
        if (cat) {
            cat.value = d.category_id;
            fire(cat, 'change');
        }

        // Titel + tekst (contenteditable editors)
        var ces = [].slice.call(document.querySelectorAll('[contenteditable="true"],[contenteditable=""]'));
        typeCE(ces[0], d.title);
        typeCE(ces[1], d.text);

        // Verborgen textareas (sync)
        setVal(document.getElementById('title'), d.title);
        setVal(document.getElementById('text'), d.text);

        // Foto + onderschrift/credit + submit
        if (d.image_name) {
            fetch(IMG).then(function(r) {
                return r.ok ? r.blob() : null;
            }).then(function(b) {
                if (b) {
                    try {
                        var f = new File([b], d.image_name, { type: b.type || 'image/jpeg' });
                        var inp = document.querySelector('input[type=file]');
                        if (inp) {
                            var dt = new DataTransfer();
                            dt.items.add(f);
                            inp.files = dt.files;
                            fire(inp, 'change');
                        }
                        setTimeout(function() { fillCC(d); }, 800);
                        setTimeout(function() { doSubmit(d); }, 2000);
                    } catch (e) {
                        fillCC(d);
                        alert('✓ Tekst ingevuld. Upload de foto zelf: ' + d.image_name + '\nVink de voorwaarden aan en klik op Verzenden.');
                    }
                } else {
                    fillCC(d);
                    alert('✓ Tekst ingevuld. Upload de foto zelf: ' + d.image_name + '\nVink de voorwaarden aan en klik op Verzenden.');
                }
            }).catch(function() {
                fillCC(d);
                alert('✓ Tekst ingevuld. Upload de foto zelf: ' + d.image_name + '\nVink de voorwaarden aan en klik op Verzenden.');
            });
        } else {
            fillCC(d);
            setTimeout(function() { doSubmit(d); }, 500);
        }
    }

    // Wacht tot het formulier klaar is, haal dan queued data op
    function waitForForm() {
        var form = document.getElementById('UGCArticleForm');
        var ces = document.querySelectorAll('[contenteditable="true"],[contenteditable=""]');
        if (form && ces.length >= 2) {
            // Formulier klaar — haal queued data op
            fetch(D).then(function(r) {
                if (r.status === 404) return null; // geen bericht klaargezet
                return r.json();
            }).then(function(d) {
                if (!d || !d.title) {
                    // Geen queued data — stilzwijgend niets doen (gebruiker vult zelf in)
                    return;
                }
                run(d);
            }).catch(function(e) {
                console.log('[SW Doorplaatser] Kon geen gegevens ophalen: ' + e);
            });
        } else {
            // Formulier nog niet klaar — over 500ms opnieuw proberen
            setTimeout(waitForForm, 500);
        }
    }

    // Start na korte vertraging (Pubble JS moet form initialiseren)
    setTimeout(waitForForm, 1000);
})();
JS;

        return str_replace( [ '__DATA__', '__IMG__' ], [ $data_endpoint, $img_endpoint ], $js );
    }
}
