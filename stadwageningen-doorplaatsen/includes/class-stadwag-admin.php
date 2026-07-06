<?php
defined( 'ABSPATH' ) || exit;

class Stadwag_Admin {

    const CATEGORIES = [ 4651 => 'Lokaal', 4562 => 'Sport', 4608 => 'Zakelijk' ];

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_settings_page' ] );
        add_action( 'add_meta_boxes',        [ $this, 'register_meta_box' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_metabox_assets' ] );
        add_action( 'wp_ajax_stadwag_queue', [ $this, 'handle_queue_ajax' ] );
    }

    // -------------------------------------------------------------------------
    // Token
    // -------------------------------------------------------------------------

    private function get_token(): string {
        $opts = get_option( 'stadwag_settings', [] );
        if ( empty( $opts['bookmarklet_token'] ) ) {
            $opts['bookmarklet_token'] = wp_generate_password( 32, false, false );
            update_option( 'stadwag_settings', $opts );
        }
        return $opts['bookmarklet_token'];
    }

    // -------------------------------------------------------------------------
    // Instellingenpagina: token + bookmarklet + uitleg
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

    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Nieuw token genereren
        if ( isset( $_POST['stadwag_regen_token'] ) && check_admin_referer( 'stadwag_regen' ) ) {
            $opts = get_option( 'stadwag_settings', [] );
            $opts['bookmarklet_token'] = wp_generate_password( 32, false, false );
            update_option( 'stadwag_settings', $opts );
            echo '<div class="notice notice-success"><p>Nieuw token aangemaakt. Installeer het userscript hieronder opnieuw (het oude werkt niet meer).</p></div>';
        }

        $token           = $this->get_token();
        $userscript_url  = rest_url( 'stadwag/v1/userscript' ) . '?token=' . rawurlencode( $token );
        $form_url        = STADWAG_TARGET_BASE . STADWAG_FORM_PATH;
        ?>
        <div class="wrap">
            <h1>Stad Wageningen Doorplaatsen</h1>

            <p>Deze plugin plaatst je WordPress-bericht door naar Stad Wageningen via je
               <strong>eigen browser</strong> — niet via de WordPress-server. Daardoor ziet de
               inzending er voor Stad Wageningen identiek uit aan een gewone bezoeker, en loop je
               niet tegen firewall- of spamblokkades aan.</p>

            <h2>Hoe werkt het?</h2>
            <ol style="max-width:760px;line-height:1.7;">
                <li>Open in WordPress het bericht dat je wilt doorplaatsen.</li>
                <li>Kies in het blok <strong>Stad Wageningen</strong> (rechts) de categorie en vul
                    eventueel onderschrift + fotocredit in.</li>
                <li>Klik op <strong>Doorplaatsen naar Stad Wageningen</strong> — het bericht wordt
                    klaargezet en de tip-de-redactie-pagina opent automatisch in een nieuw tabblad.</li>
                <li>Het userscript vult alles automatisch in (titel, tekst, categorie, foto,
                    onderschrift, fotocredit) en vinkt de voorwaarden aan.</li>
                <li>Er verschijnt een bevestigingsvenster: <strong>Verzenden naar Stad Wageningen?</strong>
                    Klik op <strong>OK</strong> om direct te verzenden, of op Annuleren om eerst te
                    controleren.</li>
            </ol>
            <p style="color:#666;">Let op: je moet ingelogd zijn op stadwageningen.nl. Het userscript
               logt niet voor je in.</p>

            <hr>

            <h2>Eenmalig instellen: het userscript</h2>
            <p>Deze plugin gebruikt een <strong>Tampermonkey-userscript</strong> in plaats van een
               bookmarklet. Het userscript draait automatisch zodra je de tip-de-redactie-pagina
               opent — je hoeft niet meer op een bookmarklet te klikken.</p>

            <h3>Stap 1: Tampermonkey installeren</h3>
            <p>Installeer de Tampermonkey-extensie voor je browser:</p>
            <ul style="list-style:disc;margin-left:20px;">
                <li><a href="https://chromewebstore.google.com/detail/tampermonkey/dhdgffkkebhmkfjojejmpbldmpobfkfo" target="_blank" rel="noopener">Chrome / Vivaldi / Edge</a></li>
                <li><a href="https://addons.mozilla.org/firefox/addon/tampermonkey/" target="_blank" rel="noopener">Firefox</a></li>
            </ul>

            <h3>Stap 2: Het userscript installeren</h3>
            <p>Klik op onderstaande link. Tampermonkey opent automatisch en vraagt of je het script
               wilt installeren. Klik op <strong>Install</strong>.</p>
            <p style="margin:16px 0;">
                <a href="<?php echo esc_url( $userscript_url ); ?>" target="_blank"
                   style="display:inline-block;padding:10px 18px;background:#2c2c2c;color:#fff;
                          border-radius:6px;text-decoration:none;font-weight:600;">
                    &darr; Installeer userscript
                </a>
            </p>
            <p class="description">Werkt de link niet? Kopieer dan de code hieronder en plak deze
               handmatig in Tampermonkey (Dashboard → + (nieuw script) → plak → Ctrl/Cmd+S):</p>
            <textarea readonly rows="6" style="width:100%;max-width:760px;font-family:monospace;font-size:11px;"
                      onclick="this.select();">Open deze URL in je browser terwijl je in WordPress bent ingelogd:
<?php echo esc_textarea( $userscript_url ); ?></textarea>

            <hr>

            <h2>Token</h2>
            <p class="description">Het userscript gebruikt dit geheime token om de berichtdata bij
               WordPress op te halen. Genereer een nieuw token als je vermoedt dat het is uitgelekt
               (daarna moet je het userscript opnieuw installeren).</p>
            <p><code style="user-select:all;"><?php echo esc_html( $token ); ?></code></p>
            <form method="post">
                <?php wp_nonce_field( 'stadwag_regen' ); ?>
                <button type="submit" name="stadwag_regen_token" value="1" class="button button-secondary"
                        onclick="return confirm('Nieuw token aanmaken? Het huidige userscript werkt daarna niet meer.');">
                    Nieuw token genereren
                </button>
            </form>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Meta box op de bericht-editor
    // -------------------------------------------------------------------------

    public function register_meta_box(): void {
        foreach ( [ 'post', 'page' ] as $screen ) {
            add_meta_box(
                'stadwag-doorplaatsen',
                'Stad Wageningen',
                [ $this, 'render_meta_box' ],
                $screen,
                'side',
                'low'
            );
        }
    }

    public function render_meta_box( \WP_Post $post ): void {
        wp_nonce_field( 'stadwag_queue', 'stadwag_nonce' );

        $queued    = get_option( 'stadwag_queued', [] );
        $is_queued = ! empty( $queued['post_id'] ) && (int) $queued['post_id'] === $post->ID;

        $saved_cat     = (int) ( get_post_meta( $post->ID, '_stadwag_last_category', true ) ?: 4651 );
        $saved_caption = get_post_meta( $post->ID, '_stadwag_last_caption', true );
        $saved_credit  = get_post_meta( $post->ID, '_stadwag_last_credit', true );
        $thumb_id      = get_post_thumbnail_id( $post->ID );

        // Status: dit bericht klaargezet?
        echo '<p id="stadwag-queued-badge" style="color:#46b450;font-weight:600;' . ( $is_queued ? '' : 'display:none;' ) . '">';
        echo '&#10003; Klaargezet voor Stad Wageningen';
        echo '</p>';

        // Categorie
        echo '<p>';
        echo '<label for="stadwag_category"><strong>Categorie</strong></label><br>';
        echo '<select name="stadwag_category" id="stadwag_category" style="width:100%;margin-top:4px;">';
        foreach ( self::CATEGORIES as $id => $label ) {
            echo '<option value="' . esc_attr( $id ) . '"' . selected( $saved_cat, $id, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '</p>';

        // Onderschrift + fotocredit (alleen relevant bij een afbeelding)
        if ( $thumb_id ) {
            echo '<p style="color:#666;font-size:12px;">&#128247; Uitgelichte afbeelding aanwezig — vul onderschrift en fotocredit in (die toon je straks bij de upload).</p>';
        }
        echo '<p>';
        echo '<label for="stadwag_caption"><strong>Onderschrift afbeelding</strong></label><br>';
        echo '<input type="text" name="stadwag_caption" id="stadwag_caption" maxlength="255" '
            . 'style="width:100%;margin-top:4px;" value="' . esc_attr( $saved_caption ) . '" placeholder="Optioneel">';
        echo '</p>';
        echo '<p>';
        echo '<label for="stadwag_credit"><strong>Fotocredit</strong></label><br>';
        echo '<input type="text" name="stadwag_credit" id="stadwag_credit" maxlength="255" '
            . 'style="width:100%;margin-top:4px;" value="' . esc_attr( $saved_credit ) . '" placeholder="Bijv. naam fotograaf">';
        echo '</p>';

        // Doorplaats-knop
        echo '<p>';
        echo '<button type="button" id="stadwag-queue-btn" class="button button-primary" style="width:100%;">';
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
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'settingsUrl' => admin_url( 'options-general.php?page=stadwag-settings' ),
            'formUrl'     => STADWAG_TARGET_BASE . STADWAG_FORM_PATH,
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX: bericht klaarzetten
    // -------------------------------------------------------------------------

    public function handle_queue_ajax(): void {
        $post_id = (int) ( $_POST['post_id'] ?? 0 );

        if ( ! $post_id || ! check_ajax_referer( 'stadwag_queue', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Ongeldige beveiligingstoken.' ], 403 );
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( [ 'message' => 'Onvoldoende rechten.' ], 403 );
        }

        $category_id = (int) ( $_POST['category_id'] ?? 4651 );
        if ( ! array_key_exists( $category_id, self::CATEGORIES ) ) {
            $category_id = 4651;
        }
        $caption = sanitize_text_field( $_POST['caption'] ?? '' );
        $credit  = sanitize_text_field( $_POST['credit']  ?? '' );

        update_post_meta( $post_id, '_stadwag_last_category', $category_id );
        update_post_meta( $post_id, '_stadwag_last_caption',  $caption );
        update_post_meta( $post_id, '_stadwag_last_credit',   $credit );

        update_option( 'stadwag_queued', [
            'post_id'     => $post_id,
            'category_id' => $category_id,
            'caption'     => $caption,
            'credit'      => $credit,
            'time'        => time(),
        ] );

        wp_send_json_success( [
            'message' => 'Klaargezet! De tip-de-redactie-pagina wordt geopend...',
            'form_url' => STADWAG_TARGET_BASE . STADWAG_FORM_PATH,
        ] );
    }
}
