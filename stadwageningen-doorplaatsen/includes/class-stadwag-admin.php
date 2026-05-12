<?php
defined( 'ABSPATH' ) || exit;

class Stadwag_Admin {

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_settings_page' ] );
        add_action( 'admin_init',            [ $this, 'register_settings' ] );
        add_action( 'add_meta_boxes',        [ $this, 'register_meta_box' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_metabox_assets' ] );
        add_action( 'wp_ajax_stadwag_doorplaatsen', [ $this, 'handle_ajax' ] );
    }

    // -------------------------------------------------------------------------
    // Settings-pagina
    // -------------------------------------------------------------------------

    public function register_settings_page(): void {
        add_options_page(
            'Stad Wageningen Doorplaatsen',
            'Stad Wageningen',
            'manage_options',
            'stadwag-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function register_settings(): void {
        register_setting(
            'stadwag_settings_group',
            'stadwag_settings',
            [ 'sanitize_callback' => [ $this, 'sanitize_settings' ] ]
        );

        add_settings_section(
            'stadwag_main_section',
            'Inloggegevens Stad Wageningen',
            static function () {
                echo '<p>Vul de inloggegevens in van uw account op stadwageningen.nl.</p>';
            },
            'stadwag-settings'
        );

        add_settings_field(
            'stadwag_email',
            'E-mailadres',
            [ $this, 'render_email_field' ],
            'stadwag-settings',
            'stadwag_main_section'
        );

        add_settings_field(
            'stadwag_password',
            'Wachtwoord',
            [ $this, 'render_password_field' ],
            'stadwag-settings',
            'stadwag_main_section'
        );
    }

    public function render_email_field(): void {
        $opts  = get_option( 'stadwag_settings', [] );
        $email = esc_attr( $opts['email'] ?? '' );
        echo '<input type="email" name="stadwag_settings[email]" value="' . $email . '" class="regular-text" autocomplete="username">';
    }

    public function render_password_field(): void {
        echo '<input type="password" name="stadwag_settings[password]" value="" class="regular-text" autocomplete="new-password">';
        echo '<p class="description">Laat leeg om het huidige wachtwoord te bewaren.</p>';
    }

    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>Stad Wageningen Doorplaatsen</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'stadwag_settings_group' );
                do_settings_sections( 'stadwag-settings' );
                submit_button( 'Instellingen opslaan' );
                ?>
            </form>
        </div>
        <?php
    }

    public function sanitize_settings( array $input ): array {
        $api     = new Stadwag_Api();
        $current = get_option( 'stadwag_settings', [] );

        $out          = [];
        $out['email'] = sanitize_email( $input['email'] ?? '' );

        $new_pass = $input['password'] ?? '';
        if ( $new_pass !== '' ) {
            $out['pass_enc'] = $api->encrypt_public( $new_pass );
        } else {
            // Bewaar bestaand versleuteld wachtwoord
            $out['pass_enc'] = $current['pass_enc'] ?? '';
        }

        return $out;
    }

    // -------------------------------------------------------------------------
    // Meta box
    // -------------------------------------------------------------------------

    public function register_meta_box(): void {
        foreach ( [ 'post', 'page' ] as $screen ) {
            add_meta_box(
                'stadwag-doorplaatsen',
                'Doorplaatsen naar Stad Wageningen',
                [ $this, 'render_meta_box' ],
                $screen,
                'side',
                'low'
            );
        }
    }

    public function render_meta_box( \WP_Post $post ): void {
        wp_nonce_field( 'stadwag_doorplaatsen_' . $post->ID, 'stadwag_nonce' );

        // Statusbadge
        $forwarded_at = get_post_meta( $post->ID, '_stadwag_forwarded_at', true );
        if ( $forwarded_at ) {
            $date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
            $date        = date_i18n( $date_format, strtotime( $forwarded_at ) );
            echo '<p class="stadwag-forwarded-badge" style="color:#46b450;font-weight:600;">';
            echo '&#10003; Doorgeplaatst op ' . esc_html( $date );
            echo '</p>';
        }

        // Categoriekeuze
        $saved_cat  = (int) ( get_post_meta( $post->ID, '_stadwag_last_category', true ) ?: 4651 );
        $categories = [ 4651 => 'Lokaal', 4562 => 'Sport', 4608 => 'Zakelijk' ];
        echo '<p>';
        echo '<label for="stadwag_category"><strong>Categorie</strong></label><br>';
        echo '<select name="stadwag_category" id="stadwag_category" style="width:100%;margin-top:4px;">';
        foreach ( $categories as $id => $label ) {
            echo '<option value="' . esc_attr( $id ) . '"' . selected( $saved_cat, $id, false ) . '>'
                . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '</p>';

        // Opmerking
        $saved_remarks = get_post_meta( $post->ID, '_stadwag_last_remarks', true );
        echo '<p>';
        echo '<label for="stadwag_remarks"><strong>Opmerking voor redactie</strong> <span style="font-weight:normal">(optioneel)</span></label><br>';
        echo '<textarea name="stadwag_remarks" id="stadwag_remarks" rows="3" maxlength="250" '
            . 'style="width:100%;margin-top:4px;" placeholder="Max. 250 tekens">'
            . esc_textarea( $saved_remarks )
            . '</textarea>';
        echo '</p>';

        // Afbeelding-info
        $thumb_id = get_post_thumbnail_id( $post->ID );
        if ( $thumb_id ) {
            echo '<p style="color:#666;font-size:12px;">&#128247; Uitgelichte afbeelding wordt meegestuurd.</p>';
        } else {
            echo '<p style="color:#999;font-size:12px;">Geen uitgelichte afbeelding ingesteld.</p>';
        }

        // Actieknop
        echo '<p>';
        echo '<button type="button" id="stadwag-submit-btn" class="button button-primary" style="width:100%;">';
        echo 'Doorplaatsen naar Stad Wageningen';
        echo '</button>';
        echo '<span id="stadwag-spinner" class="spinner" style="float:none;margin:4px 0 0 5px;visibility:hidden;"></span>';
        echo '</p>';
        echo '<div id="stadwag-feedback" style="margin-top:8px;"></div>';

        echo '<input type="hidden" id="stadwag_post_id" value="' . esc_attr( $post->ID ) . '">';
    }

    // -------------------------------------------------------------------------
    // Assets
    // -------------------------------------------------------------------------

    public function enqueue_metabox_assets( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }
        wp_enqueue_script(
            'stadwag-metabox',
            STADWAG_PLUGIN_URL . 'assets/js/metabox.js',
            [ 'jquery' ],
            STADWAG_VERSION,
            true
        );
        wp_localize_script( 'stadwag-metabox', 'stadwagAjax', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'i18n'    => [
                'sending' => 'Bezig met doorplaatsen…',
                'success' => 'Bericht succesvol doorgeplaatst!',
                'error'   => 'Fout: ',
            ],
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX handler
    // -------------------------------------------------------------------------

    public function handle_ajax(): void {
        $post_id = (int) ( $_POST['post_id'] ?? 0 );

        // Nonce-verificatie
        if ( ! $post_id || ! check_ajax_referer( 'stadwag_doorplaatsen_' . $post_id, 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Ongeldige beveiligingstoken.' ], 403 );
        }

        // Rechtencheck
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( [ 'message' => 'Onvoldoende rechten.' ], 403 );
        }

        // Invoer saniteren
        $category_id = (int) ( $_POST['category_id'] ?? 4651 );
        if ( ! in_array( $category_id, [ 4651, 4562, 4608 ], true ) ) {
            $category_id = 4651;
        }
        $remarks = sanitize_textarea_field( $_POST['remarks'] ?? '' );

        // UI-keuzes bewaren (ook bij mislukken, zodat ze beschikbaar blijven)
        update_post_meta( $post_id, '_stadwag_last_category', $category_id );
        update_post_meta( $post_id, '_stadwag_last_remarks',  $remarks );

        // Bericht doorplaatsen
        $api    = new Stadwag_Api();
        $result = $api->submit_post( $post_id, $category_id, $remarks );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        // Tijdstip vastleggen
        $now = current_time( 'mysql' );
        update_post_meta( $post_id, '_stadwag_forwarded_at', $now );

        wp_send_json_success( [
            'message'      => 'Bericht succesvol doorgeplaatst!',
            'forwarded_at' => $now,
        ] );
    }
}
