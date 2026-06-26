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
            echo '<div class="notice notice-success"><p>Nieuw token aangemaakt. Sleep de bookmarklet hieronder opnieuw naar je bladwijzerbalk (de oude werkt niet meer).</p></div>';
        }

        $token         = $this->get_token();
        $data_endpoint = rest_url( 'stadwag/v1/queued' ) . '?token=' . rawurlencode( $token );
        $img_endpoint  = rest_url( 'stadwag/v1/queued-image' ) . '?token=' . rawurlencode( $token );
        $bookmarklet   = $this->build_bookmarklet( $data_endpoint, $img_endpoint );
        $form_url      = STADWAG_TARGET_BASE . STADWAG_FORM_PATH;
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
                <li>Klik op <strong>Klaarzetten voor Stad Wageningen</strong>.</li>
                <li>Ga naar <a href="<?php echo esc_url( $form_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $form_url ); ?></a>
                    (zorg dat je daar bent ingelogd).</li>
                <li>Klik op je bookmarklet <strong>«SW invullen»</strong> — titel, tekst en categorie
                    worden automatisch ingevuld.</li>
                <li>Upload zelf de foto, controleer alles en klik op <strong>Verzenden</strong>.</li>
            </ol>

            <hr>

            <h2>Eenmalig instellen: de bookmarklet</h2>
            <p>Sleep onderstaande knop naar je bladwijzerbalk (in Vivaldi/Chrome: bladwijzerbalk
               zichtbaar maken met <code>Ctrl/Cmd&nbsp;+&nbsp;Shift&nbsp;+&nbsp;B</code>, daarna de knop
               erheen slepen):</p>

            <p style="margin:16px 0;">
                <a href="<?php echo esc_attr( $bookmarklet ); ?>"
                   onclick="alert('Sleep deze knop naar je bladwijzerbalk — niet aanklikken.'); return false;"
                   style="display:inline-block;padding:10px 18px;background:#2c2c2c;color:#fff;
                          border-radius:6px;text-decoration:none;font-weight:600;cursor:grab;">
                    &laquo; SW invullen &raquo;
                </a>
            </p>
            <p class="description">Lukt slepen niet? Maak handmatig een bladwijzer aan en plak de
               onderstaande code als URL/adres:</p>
            <textarea readonly rows="4" style="width:100%;max-width:760px;font-family:monospace;font-size:11px;"
                      onclick="this.select();"><?php echo esc_textarea( $bookmarklet ); ?></textarea>

            <hr>

            <h2>Token</h2>
            <p class="description">De bookmarklet gebruikt dit geheime token om de berichtdata bij
               WordPress op te halen. Genereer een nieuw token als je vermoedt dat het is uitgelekt
               (daarna moet je de bookmarklet opnieuw instellen).</p>
            <p><code style="user-select:all;"><?php echo esc_html( $token ); ?></code></p>
            <form method="post">
                <?php wp_nonce_field( 'stadwag_regen' ); ?>
                <button type="submit" name="stadwag_regen_token" value="1" class="button button-secondary"
                        onclick="return confirm('Nieuw token aanmaken? De huidige bookmarklet werkt daarna niet meer.');">
                    Nieuw token genereren
                </button>
            </form>
        </div>
        <?php
    }

    /**
     * Bouwt de bookmarklet-code (een javascript:-URL).
     * NOWDOC zodat PHP de JS niet interpreteert; alleen het endpoint wordt vervangen.
     */
    private function build_bookmarklet( string $data_endpoint, string $img_endpoint ): string {
        $js = <<<'JS'
javascript:(function(){var D='__ENDPOINT__',IMG='__IMG__';fetch(D).then(function(r){return r.json();}).then(function(d){if(!d||!d.title){alert('Stad Wageningen: '+((d&&(d.error||d.message))||'geen gegevens gevonden'));return;}function fire(e,t){e.dispatchEvent(new Event(t,{bubbles:true}));}function setVal(el,v){if(!el)return;var p=el.tagName==='TEXTAREA'?window.HTMLTextAreaElement.prototype:window.HTMLInputElement.prototype;var s=Object.getOwnPropertyDescriptor(p,'value').set;s.call(el,v==null?'':v);fire(el,'input');fire(el,'change');}function typeCE(ce,v){if(!ce)return;ce.focus();try{document.execCommand('selectAll',false,null);document.execCommand('insertText',false,v);}catch(err){ce.textContent=v;fire(ce,'input');}}var cat=document.getElementById('CategoryId');if(cat){cat.value=d.category_id;fire(cat,'change');}var ces=[].slice.call(document.querySelectorAll('[contenteditable="true"],[contenteditable=""]'));typeCE(ces[0],d.title);typeCE(ces[1],d.text);setVal(document.getElementById('title'),d.title);setVal(document.getElementById('text'),d.text);function fillCC(){setVal(document.querySelector("[name='caption[0]']"),d.caption);setVal(document.querySelector("[name='credit[0]']"),d.credit);}if(d.image_name){fetch(IMG).then(function(r){return r.ok?r.blob():null;}).then(function(b){var m;if(b){try{var f=new File([b],d.image_name,{type:b.type||'image/jpeg'});var inp=document.querySelector('input[type=file]');if(inp){var dt=new DataTransfer();dt.items.add(f);inp.files=dt.files;fire(inp,'change');}setTimeout(fillCC,800);m='✓ Alles ingevuld, inclusief de foto. Controleer en klik op Verzenden.';}catch(e){m='✓ Tekst ingevuld. Upload de foto zelf: '+d.image_name;}}else{m='✓ Tekst ingevuld. Upload de foto zelf: '+d.image_name;}if(d.credit){m+='\nFotocredit: '+d.credit;}if(d.caption){m+='\nOnderschrift: '+d.caption;}alert(m);}).catch(function(){alert('✓ Tekst ingevuld. Upload de foto zelf: '+d.image_name);});}else{fillCC();alert('✓ Kop, tekst en categorie ingevuld. Geen foto ingesteld.');}}).catch(function(e){alert('Kon de gegevens niet ophalen uit WordPress.\n'+e);});})();
JS;

        return str_replace( [ '__ENDPOINT__', '__IMG__' ], [ $data_endpoint, $img_endpoint ], $js );
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

        // Klaarzet-knop
        echo '<p>';
        echo '<button type="button" id="stadwag-queue-btn" class="button button-primary" style="width:100%;">';
        echo 'Klaarzetten voor Stad Wageningen';
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
            'message' => 'Klaargezet! Ga naar de Stad Wageningen-pagina en klik op je bookmarklet «SW invullen».',
        ] );
    }
}
