<?php
/**
 * Plugin Name: Cultuur in Wageningen Doorplaatser
 * Description: Plaatst een WordPress bericht door naar cultuurinwageningen.nl/agenda-nieuws-plaatsen/
 * Version:     2.0.0
 * Author:      pindsvin
 * Text Domain: cultuur-wageningen
 */

defined('ABSPATH') || exit;

class Cultuur_Wageningen_Plugin {

    const FORM_URL       = 'https://cultuurinwageningen.nl/agenda-nieuws-plaatsen/';
    const CF7_API_URL    = 'https://cultuurinwageningen.nl/wp-json/contact-form-7/v1/contact-forms/5315/feedback';
    const WPCF7_ID       = '5315';
    const WPCF7_VERSION  = '6.1.6';
    const WPCF7_LOCALE   = 'nl_NL';
    const WPCF7_UNIT_TAG = 'wpcf7-f5315-p447-o1';
    const WPCF7_POST     = '447';
    const META_KEY       = '_cultuur_wageningen_submitted';

    public function __construct() {
        add_action('add_meta_boxes',                            [$this, 'add_metabox']);
        add_action('admin_enqueue_scripts',                     [$this, 'enqueue_scripts']);
        add_action('admin_menu',                                [$this, 'add_admin_page']);
        add_action('wp_ajax_cultuur_wageningen_submit',         [$this, 'ajax_submit']);
    }

    /* ------------------------------------------------------------------
     * Admin page (opens in new tab)
     * ------------------------------------------------------------------ */

    public function add_admin_page() {
        add_submenu_page(
            null,
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

        $already_submitted = get_post_meta($post_id, self::META_KEY, true);
        $submit_nonce      = wp_create_nonce('cultuur_wageningen_submit');
        ?>
        <div class="wrap">
            <h1>Doorplaatsen naar Cultuur in Wageningen</h1>

            <?php if ($already_submitted) : ?>
                <div class="notice notice-warning inline" style="margin-bottom:16px;">
                    <p>Dit bericht is eerder verstuurd op <?php echo esc_html(date_i18n('d-m-Y H:i', $already_submitted)); ?>. Je kunt het opnieuw versturen.</p>
                </div>
            <?php endif; ?>

            <form id="ciw-doorplaats-form" enctype="multipart/form-data">

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
                                   accept=".gif,.png,.jpg,.jpeg">
                            <p class="description">Maximaal 1&nbsp;MB. Optioneel — JPEG, PNG of GIF.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Voorwaarden</th>
                        <td>
                            <label>
                                <input type="checkbox" id="ciw-acceptance" name="acceptance-335" value="1">
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
        </div>

        <script>
        var ciw = <?php echo wp_json_encode([
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'submitNonce' => $submit_nonce,
            'postId'      => $post_id,
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
        if ($hook === 'admin_page_cultuur-wageningen-doorplaats') {
            wp_enqueue_script(
                'cultuur-wageningen-doorplaats',
                plugin_dir_url(__FILE__) . 'doorplaats.js',
                [],
                '2.0.0',
                true
            );
        }
    }

    /* ------------------------------------------------------------------
     * AJAX: verstuur formulier server-side naar CF7 API (omzeilt CORS)
     * ------------------------------------------------------------------ */

    public function ajax_submit() {
        check_ajax_referer('cultuur_wageningen_submit', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Onvoldoende rechten.']);
        }

        $post_id = intval($_POST['post_id'] ?? 0);

        // Haal verse quiz-hash en honeypot-velden op van de live formulierpagina.
        $form_data = $this->fetch_form_data();

        if (!$form_data['quiz_hash']) {
            wp_send_json_error(['message' => 'Kon het formulier op Cultuur in Wageningen niet bereiken. Probeer het opnieuw.']);
        }

        $cf7_meta = $form_data['cf7_meta'];

        $fields = [
            '_wpcf7'                      => $cf7_meta['id']             ?? self::WPCF7_ID,
            '_wpcf7_version'              => $cf7_meta['version']        ?? self::WPCF7_VERSION,
            '_wpcf7_locale'               => $cf7_meta['locale']         ?? self::WPCF7_LOCALE,
            '_wpcf7_unit_tag'             => $cf7_meta['unit_tag']       ?? self::WPCF7_UNIT_TAG,
            '_wpcf7_container_post'       => $cf7_meta['container_post'] ?? self::WPCF7_POST,
            '_wpcf7_posted_data_hash'     => '',
            'naam'                        => sanitize_text_field($_POST['naam']    ?? ''),
            'email'                       => sanitize_email($_POST['email']         ?? ''),
            'titel'                       => sanitize_text_field($_POST['titel']   ?? ''),
            'bericht'                     => sanitize_textarea_field($_POST['bericht'] ?? ''),
            'quiz-467'                    => 'gelderland',
            '_wpcf7_quiz_answer_quiz-467' => $form_data['quiz_hash'],
            'acceptance-335'              => '1',
        ];

        // Honeypot: de CF7 Apps Honeypot-plugin gebruikt een willekeurige veldnaam.
        // Wij sturen het veld leeg mee (net als een gewone bezoeker die het niet ziet).
        if ($form_data['honeypot_name']) {
            $fields[$form_data['honeypot_name']] = '';
        }
        if ($form_data['honeypot_hash']) {
            $fields['stoppert-random-hash'] = $form_data['honeypot_hash'];
        }

        // Bestand verwerken via cURL CURLFile
        $tmp_path = $_FILES['file-100']['tmp_name'] ?? '';
        if ($tmp_path && is_uploaded_file($tmp_path)) {
            $file_size = $_FILES['file-100']['size'] ?? 0;
            if ($file_size > 1024 * 1024) {
                wp_send_json_error(['message' => 'De afbeelding is groter dan 1 MB.']);
            }
            $fields['file-100'] = new CURLFile(
                $tmp_path,
                $_FILES['file-100']['type'] ?? 'application/octet-stream',
                $_FILES['file-100']['name'] ?? 'afbeelding'
            );
        }

        // POST naar CF7 REST API via cURL (server-side, geen CORS-beperking)
        $ch = curl_init(self::CF7_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $fields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . ')',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Origin: https://cultuurinwageningen.nl'],
        ]);

        $raw      = curl_exec($ch);
        $curl_err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            wp_send_json_error(['message' => 'Verbindingsfout: ' . $curl_err]);
        }

        $data = json_decode($raw, true);
        if (!$data) {
            wp_send_json_error(['message' => 'Onverwacht antwoord van Cultuur in Wageningen. Response: ' . substr($raw, 0, 200)]);
        }

        if (($data['status'] ?? '') === 'mail_sent') {
            if ($post_id) {
                update_post_meta($post_id, self::META_KEY, time());
            }
            wp_send_json_success(['message' => 'Geplaatst!']);
        } else {
            $msg    = wp_strip_all_tags($data['message'] ?? '');
            $status = $data['status'] ?? 'unknown';
            $invalid = [];
            foreach (($data['invalid_fields'] ?? []) as $f) {
                $invalid[] = ($f['field'] ?? '') . ': ' . wp_strip_all_tags($f['message'] ?? '');
            }
            wp_send_json_error([
                'message' => $msg ?: 'Onbekende fout',
                'status'  => $status,
                'invalid' => $invalid,
            ]);
        }
    }

    /* ------------------------------------------------------------------
     * Helper: haal quiz-hash, honeypot-info en CF7-metadata op
     * ------------------------------------------------------------------ */

    private function fetch_form_data() {
        $result = [
            'quiz_hash'     => null,
            'honeypot_name' => null,
            'honeypot_hash' => null,
            'cf7_meta'      => [],
        ];

        $response = wp_remote_get(self::FORM_URL, [
            'timeout'    => 30,
            'user-agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . ')',
        ]);

        if (is_wp_error($response)) {
            return $result;
        }

        $body = wp_remote_retrieve_body($response);

        // Quiz-hash
        if (preg_match('/name="_wpcf7_quiz_answer_quiz-467"\s+value="([^"]+)"/', $body, $m)) {
            $result['quiz_hash'] = $m[1];
        }

        // CF7 metadata
        if (preg_match('/name="_wpcf7"\s+value="([^"]+)"/', $body, $m)) {
            $result['cf7_meta']['id'] = $m[1];
        }
        if (preg_match('/name="_wpcf7_version"\s+value="([^"]+)"/', $body, $m)) {
            $result['cf7_meta']['version'] = $m[1];
        }
        if (preg_match('/name="_wpcf7_unit_tag"\s+value="([^"]+)"/', $body, $m)) {
            $result['cf7_meta']['unit_tag'] = $m[1];
        }
        if (preg_match('/name="_wpcf7_locale"\s+value="([^"]+)"/', $body, $m)) {
            $result['cf7_meta']['locale'] = $m[1];
        }
        if (preg_match('/name="_wpcf7_container_post"\s+value="([^"]+)"/', $body, $m)) {
            $result['cf7_meta']['container_post'] = $m[1];
        }

        // Honeypot: stoppert-random-hash (verborgen veld)
        if (preg_match('/name="stoppert-random-hash"\s+value="([^"]+)"/', $body, $m)) {
            $result['honeypot_hash'] = $m[1];
        }

        // Honeypot: de willekeurige veldnaam die leeg gelaten moet worden.
        // De wrapper-span heeft class "stoppert-wrap"; daarin staat het input-veld.
        if (preg_match('/stoppert-wrap[^>]*>.*?<input[^>]+name="([a-z0-9]+)"[^>]*>/s', $body, $m)) {
            $result['honeypot_name'] = $m[1];
        }

        return $result;
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
