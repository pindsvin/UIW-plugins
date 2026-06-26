<?php
defined( 'ABSPATH' ) || exit;

/**
 * REST-endpoint dat het "klaargezette" bericht teruggeeft als JSON.
 * De bookmarklet (draaiend op stadwageningen.nl) haalt deze data op en
 * vult er de velden van het tip-de-redactie-formulier mee in.
 *
 * Beveiliging: een token in de query-string (opgeslagen in de plugin-
 * instellingen). Zonder geldig token → 401.
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
}
