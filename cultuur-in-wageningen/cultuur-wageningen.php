<?php
/**
 * Plugin Name: Cultuur in Wageningen Doorplaatser
 * Description: Plaatst een WordPress bericht door naar cultuurinwageningen.nl/agenda-nieuws-plaatsen/
 * Version:     1.4.0
 * Author:      pindsvin
 * Text Domain: cultuur-wageningen
 */

defined('ABSPATH') || exit;

class Cultuur_Wageningen_Plugin {

    const FORM_URL       = 'https://cultuurinwageningen.nl/agenda-nieuws-plaatsen/';
    const CF7_API_URL    = 'https://cultuurinwageningen.nl/wp-json/contact-form-7/v1/contact-forms/5315/feedback';
    const WPCF7_ID       = '5315';
    const WPCF7_VERSION  = '6.1.5';
    const WPCF7_LOCALE   = 'nl_NL';
    const WPCF7_UNIT_TAG = 'wpcf7-f5315-p447-o1';
    const WPCF7_POST     = '447';
    const META_KEY       = '_cultuur_wageningen_submitted';

    public function __construct() {
        add_action('add_meta_boxes',                                [$this, 'add_metabox']);
        add_action('admin_enqueue_scripts',                         [$this, 'enqueue_scripts']);
        add_action('admin_menu',                                    [$this, 'add_admin_page']);
        add_action('wp_ajax_cultuur_wageningen_save_submitted',     [$this, 'ajax_save_submitted']);
    }

    /* ------------------------------------------------------------------
     * Admin page (opens in new tab)
     * ------------------------------------------------------------------ */

    public function add_admin_page() {
        add_submenu_page(
            null,                                        // hidden — no menu entry
            'Doorplaatsen naar Cultuur in Wageningen',
            'Doorplaatsen',
            'edit_posts',
            'cultuur-wageningen-doorplaats',
            [$this, 'render_form_page']
        );
    }

    public function render_form_page() {
        $post_id = intval($_GET['post_id'] ?? 0);
        $nonce   = sanitize_text_field($_GET['nonce'] ?? '');

        if (!wp_verify_nonce($nonce, 'cultuur_wageningen_doorplaats_' . $post_id)) {
            wp_die('Ongeldig verzoek.', 'Fout', ['response' => 403]);
        }
        if (!current_user_can('edit_post', $post_id)) {
            wp_die('Onvoldoende rechten.', 'Fout', ['response' => 403]);
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_die('Bericht niet gevonden.', 'Fout', ['response' => 404]);
        }

        $user    = wp_get_current_user();
        $content = $this->html_to_plain($post->post_content);

        // Fetch quiz hash + CF7 metadata from the live form page.
        [$quiz_hash, , $cf7_meta] = $this->fetch_quiz_hash();

        $already_submitted = get_post_meta($post_id, self::META_KEY, true);
        $save_nonce        = wp_create_nonce('cultuur_wageningen_save_submitted');

        $wpcf7_id       = self::WPCF7_ID;
        $wpcf7_version  = $cf7_meta['version']        ?? self::WPCF7_VERSION;
        $wpcf7_locale   = $cf7_meta['locale']         ?? self::WPCF7_LOCALE;
        $wpcf7_unit_tag = $cf7_meta['unit_tag']       ?? self::WPCF7_UNIT_TAG;
        $wpcf7_post     = $cf7_meta['container_post'] ?? self::WPCF7_POST;
        ?>
        <div class="wrap">
            <h1>Doorplaatsen naar Cultuur in Wageningen</h1>

            <?php if ($already_submitted) : ?>
                <div class="notice notice-warning inline" style="margin-bottom:16px;">
                    <p>Dit bericht is eerder verstuurd op <?php echo esc_html(date_i18n('d-m-Y H:i', $already_submitted)); ?>. Je kunt het opnieuw versturen.</p>
                </div>
            <?php endif; ?>

            <?php if (!$quiz_hash) : ?>

                <div class="notice notice-error">
                    <p><strong>Kon het formulier op Cultuur in Wageningen niet bereiken.</strong> Sluit dit tabblad en probeer opnieuw.</p>
                </div>

            <?php else : ?>

            <form id="ciw-doorplaats-form">

                <?php /* CF7 hidden metadata */ ?>
                <input type="hidden" name="_wpcf7"                      value="<?php echo esc_attr($wpcf7_id); ?>">
                <input type="hidden" name="_wpcf7_version"              value="<?php echo esc_attr($wpcf7_version); ?>">
                <input type="hidden" name="_wpcf7_locale"               value="<?php echo esc_attr($wpcf7_locale); ?>">
                <input type="hidden" name="_wpcf7_unit_tag"             value="<?php echo esc_attr($wpcf7_unit_tag); ?>">
                <input type="hidden" name="_wpcf7_container_post"       value="<?php echo esc_attr($wpcf7_post); ?>">
                <input type="hidden" name="_wpcf7_posted_data_hash"     value="">
                <?php /* Quiz spam-bescherming */ ?>
                <input type="hidden" name="quiz-467"                    value="gelderland">
                <input type="hidden" name="_wpcf7_quiz_answer_quiz-467" value="<?php echo esc_attr($quiz_hash); ?>">
                <?php /* Honeypot: leeg laten */ ?>
                <input type="hidden" name="stoppert"                    value="">

                <table class="form-table" style="max-width:820px;">
                    <tr>
                        <th scope="row"><label for="ciw-naam">Naam</label></th>
                        <td>
                            <input type="text" id="ciw-naam" name="naam"
                                   value="<?php echo esc_attr($user->display_name); ?>"
                                   class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ciw-email">E-mail</label></th>
                        <td>
                            <input type="email" id="ciw-email" name="email"
                                   value="<?php echo esc_attr($user->user_email); ?>"
                                   class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ciw-titel">Titel</label></th>
                        <td>
                            <input type="text" id="ciw-titel" name="titel"
                                   value="<?php echo esc_attr($post->post_title); ?>"
                                   class="large-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ciw-bericht">Bericht</label></th>
                        <td>
                            <textarea id="ciw-bericht" name="bericht"
                                      rows="12" class="large-text"
                                      required><?php echo esc_textarea($content); ?></textarea>
                            <p class="description">Minimaal 50 tekens. Je kunt de tekst hier nog aanpassen voor verzending.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ciw-afbeelding">Afbeelding</label></th>
                        <td>
                            <input type="file" id="ciw-afbeelding" name="file-100"
                                   accept="image/jpeg,image/png,image/gif">
                            <p class="description">Maximaal 1&nbsp;MB. Optioneel — JPEG, PNG of GIF.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Voorwaarden</th>
                        <td>
                            <label>
                                <input type="checkbox" id="ciw-acceptance"
                                       name="acceptance-335" value="on">
                                Ik ga akkoord met de voorwaarden van Cultuur in Wageningen
                            </label>
                            <p class="description">Verplicht — de verzendknop wordt actief na aanvinken.</p>
                        </td>
                    </tr>
                </table>

                <p style="margin-top:20px;">
                    <button type="submit" id="ciw-submit-btn"
                            class="button button-primary button-large" disabled>
                        Versturen naar Cultuur in Wageningen
                    </button>
                    <span id="ciw-spinner" class="spinner"
                          style="float:none;visibility:hidden;margin:0 8px 0 4px;"></span>
                </p>
            </form>

            <div id="ciw-result" style="margin-top:20px;max-width:820px;"></div>

            <?php endif; // quiz_hash ?>
        </div>

        <script>
        var ciw = <?php echo wp_json_encode([
            'cfApiUrl'  => self::CF7_API_URL,
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'saveNonce' => $save_nonce,
            'postId'    => $post_id,
        ]); ?>;
        </script>
        <?php
    }

    /* ------------------------------------------------------------------
     * Metabox (sidebar on post edit screen)
     * ------------------------------------------------------------------ */

    public function add_metabox() {
        add_meta_box(
            'cultuur-wageningen',
            'Cultuur in Wageningen',
            [$this, 'render_metabox'],
            'post',
            'side',
            'default'
        );
    }

    public function render_metabox($post) {
        $submitted = get_post_meta($post->ID, self::META_KEY, true);
        $nonce     = wp_create_nonce('cultuur_wageningen_doorplaats_' . $post->ID);
        $url       = add_query_arg([
            'page'    => 'cultuur-wageningen-doorplaats',
            'post_id' => $post->ID,
            'nonce'   => $nonce,
        ], admin_url('admin.php'));
        ?>
        <div id="cultuur-wageningen-box">
            <?php if ($submitted) : ?>
                <p style="color:#2a9000;margin:0 0 8px;">
                    ✓ Verstuurd op <?php echo esc_html(date_i18n('d-m-Y H:i', $submitted)); ?>
                </p>
            <?php endif; ?>
            <p style="margin:0;">
                <a href="<?php echo esc_url($url); ?>"
                   target="_blank"
                   class="button button-primary">
                    Doorplaatsen &rarr;
                </a>
            </p>
        </div>
        <?php
    }

    /* ------------------------------------------------------------------
     * Scripts
     * ------------------------------------------------------------------ */

    public function enqueue_scripts($hook) {
        // doorplaats.js alleen laden op de doorplaats-pagina (nieuw tabblad).
        if ($hook === 'admin_page_cultuur-wageningen-doorplaats') {
            wp_enqueue_script(
                'cultuur-wageningen-doorplaats',
                plugin_dir_url(__FILE__) . 'doorplaats.js',
                [],
                '1.4.0',
                true   // footer — zodat <script>var ciw=…</script> al beschikbaar is
            );
        }
    }

    /* ------------------------------------------------------------------
     * AJAX: sla tijdstip op na succesvolle browsersubmissie
     * ------------------------------------------------------------------ */

    public function ajax_save_submitted() {
        check_ajax_referer('cultuur_wageningen_save_submitted', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Onvoldoende rechten.']);
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error(['message' => 'Ongeldig bericht-ID.']);
        }

        update_post_meta($post_id, self::META_KEY, time());
        wp_send_json_success(['message' => 'Opgeslagen.']);
    }

    /* ------------------------------------------------------------------
     * Helper: haal quiz-hash + CF7-metadata op van de live formulierpagina
     * ------------------------------------------------------------------ */

    private function fetch_quiz_hash() {
        $response = wp_remote_get(self::FORM_URL, [
            'timeout'    => 30,
            'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
        ]);

        if (is_wp_error($response)) {
            return [null, [], []];
        }

        $body = wp_remote_retrieve_body($response);

        if (!preg_match('/name="_wpcf7_quiz_answer_quiz-467"\s+value="([^"]+)"/', $body, $m)) {
            return [null, [], []];
        }

        $quiz_hash = $m[1];
        $cookies   = wp_remote_retrieve_cookies($response);

        $cf7_meta = [];
        if (preg_match('/name="_wpcf7_version"\s+value="([^"]+)"/', $body, $vm)) {
            $cf7_meta['version'] = $vm[1];
        }
        if (preg_match('/name="_wpcf7_unit_tag"\s+value="([^"]+)"/', $body, $utm)) {
            $cf7_meta['unit_tag'] = $utm[1];
        }
        if (preg_match('/name="_wpcf7_locale"\s+value="([^"]+)"/', $body, $lm)) {
            $cf7_meta['locale'] = $lm[1];
        }
        if (preg_match('/name="_wpcf7_container_post"\s+value="([^"]+)"/', $body, $cpm)) {
            $cf7_meta['container_post'] = $cpm[1];
        }

        return [$quiz_hash, $cookies, $cf7_meta];
    }

    /* ------------------------------------------------------------------
     * Helper: HTML post-inhoud omzetten naar platte tekst
     * ------------------------------------------------------------------ */

    private function html_to_plain($html) {
        $text = preg_replace('/<\/p\s*>/i', "\n\n", $html);
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = wp_strip_all_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }
}

new Cultuur_Wageningen_Plugin();
