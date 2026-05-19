<?php
/**
 * Plugin Name: Cultuur in Wageningen Doorplaatser
 * Description: Plaatst een WordPress bericht door naar cultuurinwageningen.nl/agenda-nieuws-plaatsen/
 * Version:     1.0.0
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
        add_action('add_meta_boxes',                          [$this, 'add_metabox']);
        add_action('admin_enqueue_scripts',                   [$this, 'enqueue_scripts']);
        add_action('wp_ajax_cultuur_wageningen_preview',      [$this, 'ajax_preview']);
        add_action('wp_ajax_cultuur_wageningen_submit',       [$this, 'ajax_submit']);
    }

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
        wp_nonce_field('cultuur_wageningen_submit', 'cultuur_wageningen_nonce');
        $submitted = get_post_meta($post->ID, self::META_KEY, true);
        ?>
        <div id="cultuur-wageningen-box">
            <?php if ($submitted) : ?>
                <p style="color:#2a9000;margin:0 0 8px;">
                    ✓ Verstuurd op <?php echo esc_html(date_i18n('d-m-Y H:i', $submitted)); ?>
                </p>
            <?php endif; ?>
            <p style="margin:0 0 8px;">
                <button type="button"
                        id="cultuur-wageningen-preview-btn"
                        class="button button-secondary"
                        data-post-id="<?php echo esc_attr($post->ID); ?>">
                    Controleer voor verzending
                </button>
            </p>
            <div id="cultuur-wageningen-preview" style="display:none;margin:8px 0;padding:8px;background:#f6f7f7;border:1px solid #ddd;font-size:12px;line-height:1.5;"></div>
            <p style="margin:0 0 8px;display:none;" id="cultuur-wageningen-submit-wrap">
                <button type="button"
                        id="cultuur-wageningen-btn"
                        class="button button-primary"
                        data-post-id="<?php echo esc_attr($post->ID); ?>">
                    Bevestig en verstuur
                </button>
            </p>
            <div id="cultuur-wageningen-status"></div>
        </div>
        <?php
    }

    public function enqueue_scripts($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        wp_enqueue_script(
            'cultuur-wageningen-admin',
            plugin_dir_url(__FILE__) . 'admin.js',
            ['jquery'],
            '1.1.0',
            true
        );
        wp_localize_script('cultuur-wageningen-admin', 'cultuurWageningen', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
    }

    public function ajax_preview() {
        check_ajax_referer('cultuur_wageningen_submit', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Onvoldoende rechten.']);
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $post    = get_post($post_id);

        if (!$post) {
            wp_send_json_error(['message' => 'Bericht niet gevonden.']);
        }

        $user    = wp_get_current_user();
        $content = $this->html_to_plain($post->post_content);
        $image   = $this->get_featured_image($post_id);

        if (mb_strlen($content) < 50) {
            wp_send_json_error(['message' => 'De berichttekst is te kort (minimaal 50 tekens vereist).']);
        }

        $preview = [
            'naam'   => $user->display_name,
            'email'  => $user->user_email,
            'titel'  => $post->post_title,
            'bericht' => mb_substr($content, 0, 300) . (mb_strlen($content) > 300 ? '…' : ''),
            'afbeelding' => $image ? $image['name'] : '(geen afbeelding — wordt niet meegestuurd)',
        ];

        wp_send_json_success($preview);
    }

    public function ajax_submit() {
        check_ajax_referer('cultuur_wageningen_submit', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Onvoldoende rechten.']);
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $post    = get_post($post_id);

        if (!$post) {
            wp_send_json_error(['message' => 'Bericht niet gevonden.']);
        }

        $user = wp_get_current_user();

        [$quiz_hash, $cookies] = $this->fetch_quiz_hash();

        if (!$quiz_hash) {
            wp_send_json_error(['message' => 'Kon het Cultuur Wageningen formulier niet bereiken. Probeer het opnieuw.']);
        }

        $image = $this->get_featured_image($post_id);
        $result = $this->submit_form($post, $user, $quiz_hash, $cookies, $image);

        if ($result['success']) {
            update_post_meta($post_id, self::META_KEY, time());
            wp_send_json_success(['message' => $result['message']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * GET the form page and extract the CF7 quiz answer hash + session cookies.
     *
     * @return array [string|null $quiz_hash, array $cookies]
     */
    private function fetch_quiz_hash() {
        $response = wp_remote_get(self::FORM_URL, [
            'timeout'    => 30,
            'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
        ]);

        if (is_wp_error($response)) {
            return [null, []];
        }

        $body = wp_remote_retrieve_body($response);

        if (!preg_match('/name="_wpcf7_quiz_answer_quiz-467"\s+value="([^"]+)"/', $body, $m)) {
            return [null, []];
        }

        $cookies = wp_remote_retrieve_cookies($response);

        return [$m[1], $cookies];
    }

    /**
     * Return info about the post's featured image, or null if none / too large.
     *
     * @return array|null ['path'=>string, 'name'=>string, 'mime'=>string]
     */
    private function get_featured_image($post_id) {
        $thumb_id = get_post_thumbnail_id($post_id);
        if (!$thumb_id) {
            return null;
        }

        $file = get_attached_file($thumb_id);
        if (!$file || !file_exists($file)) {
            return null;
        }

        $allowed_mime = ['image/jpeg', 'image/png', 'image/gif'];
        $mime         = get_post_mime_type($thumb_id);
        if (!in_array($mime, $allowed_mime, true)) {
            return null;
        }

        // CF7 form states max 1 MB
        if (filesize($file) > 1 * 1024 * 1024) {
            return null;
        }

        return [
            'path' => $file,
            'name' => basename($file),
            'mime' => $mime,
        ];
    }

    /**
     * Build a multipart/form-data POST body and send it to the CF7 REST API.
     */
    private function submit_form($post, $user, $quiz_hash, $cookies, $image) {
        $content = $this->html_to_plain($post->post_content);

        if (mb_strlen($content) < 50) {
            return ['success' => false, 'message' => 'De berichttekst is te kort (minimaal 50 tekens vereist).'];
        }

        $fields = [
            '_wpcf7'                      => self::WPCF7_ID,
            '_wpcf7_version'              => self::WPCF7_VERSION,
            '_wpcf7_locale'               => self::WPCF7_LOCALE,
            '_wpcf7_unit_tag'             => self::WPCF7_UNIT_TAG,
            '_wpcf7_container_post'       => self::WPCF7_POST,
            '_wpcf7_posted_data_hash'     => '',
            'naam'                        => $user->display_name,
            'email'                       => $user->user_email,
            'titel'                       => $post->post_title,
            'bericht'                     => $content,
            'acceptance-335'              => '1',
            'stoppert'                    => '',
            'quiz-467'                    => 'Gelderland',
            '_wpcf7_quiz_answer_quiz-467' => $quiz_hash,
        ];

        $boundary = '----------WPCultuurWageningen' . md5(uniqid('', true));
        $body     = $this->build_multipart_body($boundary, $fields, $image);

        $cookie_header = '';
        foreach ($cookies as $c) {
            $cookie_header .= $c->name . '=' . $c->value . '; ';
        }

        $response = wp_remote_post(self::CF7_API_URL, [
            'timeout' => 60,
            'headers' => [
                'Content-Type'   => 'multipart/form-data; boundary=' . $boundary,
                'Accept'         => 'application/json',
                'Cookie'         => rtrim($cookie_header, '; '),
            ],
            'body'    => $body,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => 'Verbindingsfout: ' . $response->get_error_message()];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $data      = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($data['status']) && $data['status'] === 'mail_sent') {
            return ['success' => true, 'message' => 'Bericht succesvol geplaatst op Cultuur in Wageningen!'];
        }

        $server_msg = $data['message'] ?? '';
        if ($server_msg) {
            return ['success' => false, 'message' => 'Plaatsing mislukt: ' . wp_strip_all_tags($server_msg)];
        }

        return ['success' => false, 'message' => 'Plaatsing mislukt (HTTP ' . $http_code . '). Controleer of de berichttekst minimaal 50 tekens bevat.'];
    }

    /**
     * Build a raw multipart/form-data body string.
     */
    private function build_multipart_body($boundary, array $fields, $image) {
        $eol  = "\r\n";
        $body = '';

        foreach ($fields as $name => $value) {
            $body .= "--{$boundary}{$eol}";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"{$eol}{$eol}";
            $body .= $value . $eol;
        }

        if ($image) {
            $file_data = file_get_contents($image['path']);
            if ($file_data !== false) {
                $body .= "--{$boundary}{$eol}";
                $body .= "Content-Disposition: form-data; name=\"file-100\"; filename=\"{$image['name']}\"{$eol}";
                $body .= "Content-Type: {$image['mime']}{$eol}{$eol}";
                $body .= $file_data . $eol;
            }
        }

        $body .= "--{$boundary}--{$eol}";

        return $body;
    }

    /**
     * Convert HTML post content to plain text suitable for the CF7 textarea.
     */
    private function html_to_plain($html) {
        // Preserve paragraph/line breaks before stripping tags
        $text = preg_replace('/<\/p\s*>/i', "\n\n", $html);
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = wp_strip_all_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }
}

new Cultuur_Wageningen_Plugin();
