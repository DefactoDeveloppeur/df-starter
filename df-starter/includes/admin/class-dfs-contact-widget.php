<?php

if (!defined('ABSPATH')) {
    exit;
}

class DFS_Contact_Widget
{
    const NONCE_ACTION = 'dfs_send_mail';
    const RECIPIENT = 'assistance@studiodefacto.com';

    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_footer', [$this, 'render']);
        add_action('wp_ajax_dfs_send_mail', [$this, 'ajax_send_mail']);
    }

    public function enqueue_assets(): void
    {
        wp_enqueue_style(
            'dfs-contact-widget',
            DFS_URL . 'assets/css/contact-widget.css',
            [],
            DFS_VERSION
        );

        wp_enqueue_script(
            'dfs-contact-widget',
            DFS_URL . 'assets/js/contact-widget.js',
            [],
            DFS_VERSION,
            true
        );

        wp_localize_script('dfs-contact-widget', 'dfsContactWidget', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::NONCE_ACTION),
            'i18n'    => [
                'sent'  => __('Email envoyé! ✅', 'df-starter'),
                'error' => __('Une erreur s\'est produite, veuillez réessayer', 'df-starter'),
            ],
        ]);
    }

    public function render(): void
    {
        ?>
        <button id="client-help-btn" type="button" aria-label="<?php esc_attr_e('Demande d\'assistance', 'df-starter'); ?>">?</button>
        <div id="client-help-form" hidden>
            <p><?php esc_html_e('Demande d\'assistance', 'df-starter'); ?></p>
            <div id="client-help-status" hidden></div>
            <input id="objet-textarea" placeholder="<?php esc_attr_e('Objet...', 'df-starter'); ?>" type="text">
            <textarea id="message-textarea" placeholder="<?php esc_attr_e('Message...', 'df-starter'); ?>"></textarea>
            <button id="client-help-send" type="button"><?php esc_html_e('Envoyer', 'df-starter'); ?></button>
        </div>
        <?php
    }

    public function ajax_send_mail(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Non autorisé'], 403);
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], self::NONCE_ACTION)) {
            wp_send_json_error(['message' => 'Nonce invalide'], 403);
        }

        $subject = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

        if (!$subject) {
            wp_send_json_error(['message' => 'Veuillez indiquer un objet valide']);
        }
        if (!$message) {
            wp_send_json_error(['message' => 'Veuillez indiquer un message valide']);
        }

        $current_user = wp_get_current_user();
        $body = sprintf(
            "Message de %s\n%s\n\n%s",
            $current_user->user_login,
            $current_user->user_email,
            $message
        );

        $sent = wp_mail(self::RECIPIENT, $subject, $body);

        if ($sent) {
            wp_send_json_success(['message' => 'Email envoyé']);
        }

        wp_send_json_error(['message' => 'Une erreur s\'est produite en envoyant le mail']);
    }
}
