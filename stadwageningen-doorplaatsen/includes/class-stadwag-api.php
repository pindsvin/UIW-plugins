<?php
defined( 'ABSPATH' ) || exit;

class Stadwag_Api {

    // -------------------------------------------------------------------------
    // Credential storage
    // -------------------------------------------------------------------------

    /**
     * Versleuteld opslaan in wp_options.
     */
    public function save_credentials( string $email, string $password ): void {
        $current = get_option( 'stadwag_settings', [] );
        $current['email']    = sanitize_email( $email );
        $current['pass_enc'] = $this->encrypt( $password );
        update_option( 'stadwag_settings', $current );
    }

    /**
     * @return array{email:string,password:string}|WP_Error
     */
    public function get_credentials(): array|\WP_Error {
        $opts = get_option( 'stadwag_settings', [] );
        if ( empty( $opts['email'] ) || empty( $opts['pass_enc'] ) ) {
            return new \WP_Error(
                'stadwag_no_credentials',
                'Geen Stad Wageningen inloggegevens ingesteld. Ga naar Instellingen → Stad Wageningen Doorplaatsen.'
            );
        }
        $password = $this->decrypt( $opts['pass_enc'] );
        if ( $password === false ) {
            return new \WP_Error( 'stadwag_decrypt_error', 'Wachtwoord ontsleutelen mislukt.' );
        }
        return [ 'email' => $opts['email'], 'password' => $password ];
    }

    /**
     * Publieke wrapper zodat Stadwag_Admin encryptie kan aanroepen.
     */
    public function encrypt_public( string $plaintext ): string {
        return $this->encrypt( $plaintext );
    }

    // -------------------------------------------------------------------------
    // Encryptie helpers (AES-256-CBC, sleutel van wp_salt)
    // -------------------------------------------------------------------------

    private function encrypt( string $plaintext ): string {
        $key    = substr( hash( 'sha256', wp_salt( 'auth' ), true ), 0, 32 );
        $iv     = random_bytes( 16 );
        $cipher = openssl_encrypt( $plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        return base64_encode( $iv . $cipher );
    }

    private function decrypt( string $ciphertext ): string|false {
        $key  = substr( hash( 'sha256', wp_salt( 'auth' ), true ), 0, 32 );
        $raw  = base64_decode( $ciphertext );
        if ( strlen( $raw ) <= 16 ) {
            return false;
        }
        $iv   = substr( $raw, 0, 16 );
        $data = substr( $raw, 16 );
        return openssl_decrypt( $data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
    }

    // -------------------------------------------------------------------------
    // Sessiebeheer
    // -------------------------------------------------------------------------

    private function get_session_transient_key(): string {
        $opts = get_option( 'stadwag_settings', [] );
        return 'stadwag_session_' . md5( ( $opts['email'] ?? '' ) . ( $opts['pass_enc'] ?? '' ) );
    }

    /**
     * Geeft een geldige cookie-string terug (uit transient of via verse login).
     *
     * @return string|WP_Error
     */
    public function get_valid_session(): string|\WP_Error {
        $cached = get_transient( $this->get_session_transient_key() );
        if ( $cached !== false ) {
            return $cached;
        }
        return $this->do_login();
    }

    /**
     * Logt in op Stad Wageningen en slaat sessie-cookie op als transient.
     *
     * @return string|WP_Error  Cookie-string bij succes
     */
    private function do_login(): string|\WP_Error {
        $creds = $this->get_credentials();
        if ( is_wp_error( $creds ) ) {
            return $creds;
        }

        $login_url = STADWAG_TARGET_BASE . STADWAG_LOGIN_PATH;

        // Stap 1: GET login-pagina om CSRF-token te halen
        $ch = curl_init( $login_url );
        curl_setopt_array( $ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; WP-Doorplaatsen/1.0)',
            CURLOPT_TIMEOUT        => 30,
        ] );
        $response    = curl_exec( $ch );
        $header_size = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
        $http_code   = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $curl_error  = curl_error( $ch );
        curl_close( $ch );

        if ( $curl_error ) {
            return new \WP_Error( 'stadwag_curl_error', 'cURL fout (login GET): ' . $curl_error );
        }
        if ( $http_code !== 200 ) {
            return new \WP_Error( 'stadwag_login_page_failed', 'Login-pagina niet bereikbaar (HTTP ' . $http_code . ').' );
        }

        $login_html     = substr( $response, $header_size );
        $login_headers  = substr( $response, 0, $header_size );

        // Extraheer __RequestVerificationToken uit login-pagina
        $aft = $this->extract_request_verification_token( $login_html );
        if ( $aft === '' ) {
            return new \WP_Error( 'stadwag_token_missing', '__RequestVerificationToken niet gevonden op login-pagina.' );
        }

        // Haal eventuele init-cookies op (antiforgery cookie)
        preg_match_all( '/^Set-Cookie:\s*([^;\r\n]+)/mi', $login_headers, $init_cookie_matches );
        $init_cookie = implode( '; ', $init_cookie_matches[1] ?? [] );

        // Stap 2: POST credentials
        $ch = curl_init( $login_url );
        curl_setopt_array( $ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query( [
                '__RequestVerificationToken' => $aft,
                'Email'                      => $creds['email'],
                'Password'                   => $creds['password'],
                'RememberMe'                 => 'false',
            ] ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false, // 302 = succes
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; WP-Doorplaatsen/1.0)',
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => array_filter( [
                'Content-Type: application/x-www-form-urlencoded',
                'Referer: ' . $login_url,
                $init_cookie ? 'Cookie: ' . $init_cookie : '',
            ] ),
        ] );
        $response    = curl_exec( $ch );
        $header_size = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
        $http_code   = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $curl_error  = curl_error( $ch );
        curl_close( $ch );

        if ( $curl_error ) {
            return new \WP_Error( 'stadwag_curl_error', 'cURL fout (login POST): ' . $curl_error );
        }

        // HTTP 302 = succesvol ingelogd, HTTP 200 = verkeerde gegevens
        if ( $http_code === 200 ) {
            return new \WP_Error( 'stadwag_login_failed', 'Inloggen mislukt. Controleer e-mailadres en wachtwoord.' );
        }
        if ( $http_code !== 302 ) {
            return new \WP_Error( 'stadwag_login_failed', 'Onverwacht antwoord bij inloggen (HTTP ' . $http_code . ').' );
        }

        $post_headers = substr( $response, 0, $header_size );

        // Verzamel alle Set-Cookie headers (sessie + antiforgery)
        preg_match_all( '/^Set-Cookie:\s*([^;\r\n]+)/mi', $post_headers, $cookie_matches );
        if ( empty( $cookie_matches[1] ) ) {
            return new \WP_Error( 'stadwag_no_cookie', 'Geen sessie-cookie ontvangen na inloggen.' );
        }

        // Merge init-cookies en login-cookies
        $cookie_string = $this->merge_cookies( $init_cookie, $cookie_matches[1] );

        set_transient( $this->get_session_transient_key(), $cookie_string, 20 * MINUTE_IN_SECONDS );

        return $cookie_string;
    }

    // -------------------------------------------------------------------------
    // Formulier-tokens ophalen
    // -------------------------------------------------------------------------

    /**
     * Haalt de CSRF-tokens op van de tip-de-redactie pagina.
     *
     * @return array{rvt:string,rna:string,cookie:string}|WP_Error
     */
    public function fetch_form_tokens( string $cookie ): array|\WP_Error {
        $form_url = STADWAG_TARGET_BASE . STADWAG_FORM_PATH;

        $ch = curl_init( $form_url );
        curl_setopt_array( $ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; WP-Doorplaatsen/1.0)',
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Cookie: '   . $cookie,
                'Referer: '  . STADWAG_TARGET_BASE . '/',
            ],
        ] );
        $response    = curl_exec( $ch );
        $header_size = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
        $http_code   = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $curl_error  = curl_error( $ch );
        curl_close( $ch );

        if ( $curl_error ) {
            return new \WP_Error( 'stadwag_curl_error', 'cURL fout (formulier ophalen): ' . $curl_error );
        }

        // 302 naar /login = sessie verlopen
        if ( $http_code === 302 ) {
            return new \WP_Error( 'stadwag_session_expired', 'Sessie verlopen.' );
        }
        if ( $http_code !== 200 ) {
            return new \WP_Error( 'stadwag_fetch_form_failed', 'Formulierpagina ophalen mislukt (HTTP ' . $http_code . ').' );
        }

        $raw_headers = substr( $response, 0, $header_size );
        $body        = substr( $response, $header_size );

        // Extraheer __RequestVerificationToken
        $rvt = $this->extract_request_verification_token( $body );
        if ( $rvt === '' ) {
            return new \WP_Error( 'stadwag_token_missing', '__RequestVerificationToken niet gevonden op formulierpagina.' );
        }

        // Extraheer RequestName_Aes (per-request versleuteld token)
        $rna = '';
        if ( preg_match( '/<input[^>]+name="RequestName_Aes"[^>]*>/i', $body, $tag_match ) ) {
            if ( preg_match( '/value="([^"]+)"/', $tag_match[0], $val_match ) ) {
                $rna = $val_match[1];
            }
        }

        // Merge eventuele verse cookies uit deze response
        preg_match_all( '/^Set-Cookie:\s*([^;\r\n]+)/mi', $raw_headers, $fresh_cookie_matches );
        $merged_cookie = $this->merge_cookies( $cookie, $fresh_cookie_matches[1] ?? [] );

        return [
            'rvt'    => $rvt,
            'rna'    => $rna,
            'cookie' => $merged_cookie,
        ];
    }

    // -------------------------------------------------------------------------
    // Bericht doorplaatsen
    // -------------------------------------------------------------------------

    /**
     * Plaatst een WordPress-bericht door naar Stad Wageningen.
     *
     * @return true|WP_Error
     */
    public function submit_post( int $post_id, int $category_id, string $remarks ): true|\WP_Error {

        // Stap 1: credentials ophalen
        $creds = $this->get_credentials();
        if ( is_wp_error( $creds ) ) {
            return $creds;
        }

        // Stap 2: sessie ophalen (transient of verse login)
        $cookie = $this->get_valid_session();
        if ( is_wp_error( $cookie ) ) {
            return $cookie;
        }

        // Stap 3: formulier-tokens ophalen; bij verlopen sessie eenmaal opnieuw inloggen
        $tokens = $this->fetch_form_tokens( $cookie );
        if ( is_wp_error( $tokens ) && $tokens->get_error_code() === 'stadwag_session_expired' ) {
            delete_transient( $this->get_session_transient_key() );
            $cookie = $this->do_login();
            if ( is_wp_error( $cookie ) ) {
                return $cookie;
            }
            $tokens = $this->fetch_form_tokens( $cookie );
        }
        if ( is_wp_error( $tokens ) ) {
            return $tokens;
        }

        // Stap 4: WordPress-berichtdata ophalen en saniteren
        $post    = get_post( $post_id );
        $title   = mb_substr( wp_strip_all_tags( $post->post_title ), 0, 640 );
        $content = wp_strip_all_tags( apply_filters( 'the_content', $post->post_content ) );
        $text    = mb_substr( $content, 0, 3000 );
        $remarks = mb_substr( $remarks, 0, 250 );

        // Stap 5: uitgelichte afbeelding voorbereiden
        $image_file = null;
        $caption_0  = '';
        $credit_0   = '';
        $thumb_id   = get_post_thumbnail_id( $post_id );

        if ( $thumb_id ) {
            $image_path = get_attached_file( $thumb_id );
            $image_mime = get_post_mime_type( $thumb_id );
            $allowed    = [ 'image/jpeg', 'image/gif', 'image/png' ];

            if ( $image_path && file_exists( $image_path ) && in_array( $image_mime, $allowed, true ) ) {
                $image_file = new \CURLFile( $image_path, $image_mime, basename( $image_path ) );
                $caption_0  = get_the_title( $thumb_id );
                $credit_0   = get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );

                // credit[0] is verplicht — fallback naar bijschrift of standaard credit
                if ( empty( $credit_0 ) ) {
                    $credit_0 = $caption_0 ?: 'Uit in Wageningen';
                }
            }
        }

        // Stap 6: POST-velden samenstellen
        $fields = [
            'CategoryId'                 => (string) $category_id,
            'title'                      => $title,
            'text'                       => $text,
            'Url'                        => '',
            'remarks'                    => $remarks,
            'okGeneralConditions'        => 'true',
            'hp_website'                 => '', // honeypot: ALTIJD leeg
            'RequestName_Aes'            => $tokens['rna'],
            '__RequestVerificationToken' => $tokens['rvt'],
        ];

        if ( $image_file ) {
            $fields['file']       = $image_file;
            $fields['caption[0]'] = $caption_0;
            $fields['credit[0]']  = $credit_0;
        }

        // Stap 7: multipart POST uitvoeren
        $form_url = STADWAG_TARGET_BASE . STADWAG_FORM_PATH;

        $ch = curl_init( $form_url );
        curl_setopt_array( $ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $fields, // array = automatisch multipart/form-data
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,   // 302 = succes
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; WP-Doorplaatsen/1.0)',
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                'Cookie: '  . $tokens['cookie'],
                'Referer: ' . $form_url,
                'Origin: '  . STADWAG_TARGET_BASE,
            ],
        ] );
        $response   = curl_exec( $ch );
        $header_size = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
        $http_code  = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $curl_error = curl_error( $ch );
        curl_close( $ch );

        if ( $curl_error ) {
            return new \WP_Error( 'stadwag_curl_error', 'cURL fout (formulier versturen): ' . $curl_error );
        }

        // HTTP 302 = formulier geaccepteerd
        if ( $http_code === 302 ) {
            return true;
        }

        // HTTP 200 = validatiefout; probeer foutmelding te extraheren
        $body       = substr( $response, $header_size );
        $error_hint = $this->extract_form_error( $body );

        return new \WP_Error(
            'stadwag_submit_failed',
            'Formulier niet geaccepteerd (HTTP ' . $http_code . ')' . ( $error_hint ? ': ' . $error_hint : '.' )
        );
    }

    // -------------------------------------------------------------------------
    // Hulpfuncties
    // -------------------------------------------------------------------------

    /**
     * Extraheert __RequestVerificationToken ongeacht de volgorde van attributen.
     */
    private function extract_request_verification_token( string $html ): string {
        // Match de volledige <input>-tag (attribuutvolgorde-onafhankelijk)
        if ( preg_match( '/<input[^>]+name="__RequestVerificationToken"[^>]*>/i', $html, $tag_match ) ) {
            if ( preg_match( '/value="([^"]+)"/', $tag_match[0], $val_match ) ) {
                return $val_match[1];
            }
        }
        // Fallback: <meta name="__RequestVerificationToken" content="…">
        if ( preg_match( '/<meta[^>]+name="__RequestVerificationToken"[^>]+content="([^"]+)"/i', $html, $m ) ) {
            return $m[1];
        }
        return '';
    }

    /**
     * Voegt nieuwe cookie-paren samen in een bestaande cookie-string.
     * Nieuwere waarden overschrijven oudere bij gelijke naam.
     *
     * @param string   $existing  Huidige "key=value; key2=value2" cookie-string
     * @param string[] $new_pairs Array van "key=value" strings
     */
    private function merge_cookies( string $existing, array $new_pairs ): string {
        $map = [];
        if ( $existing !== '' ) {
            foreach ( explode( '; ', $existing ) as $pair ) {
                [ $k, $v ] = array_pad( explode( '=', $pair, 2 ), 2, '' );
                $map[ trim( $k ) ] = trim( $v );
            }
        }
        foreach ( $new_pairs as $pair ) {
            [ $k, $v ] = array_pad( explode( '=', $pair, 2 ), 2, '' );
            $k = trim( $k );
            if ( $k !== '' ) {
                $map[ $k ] = trim( $v );
            }
        }
        return implode( '; ', array_map(
            static fn( $k, $v ) => "$k=$v",
            array_keys( $map ),
            $map
        ) );
    }

    /**
     * Probeert een leesbare foutmelding uit de formulierrespons te halen.
     */
    private function extract_form_error( string $body ): string {
        // ASP.NET validation-summary
        if ( preg_match( '/<div[^>]+class="[^"]*validation-summary-errors[^"]*"[^>]*>(.*?)<\/div>/si', $body, $m ) ) {
            return wp_strip_all_tags( $m[1] );
        }
        // Losse veldfouten
        if ( preg_match_all( '/<span[^>]+class="[^"]*field-validation-error[^"]*"[^>]*>([^<]+)<\/span>/i', $body, $m ) ) {
            return implode( '; ', $m[1] );
        }
        return '';
    }
}
