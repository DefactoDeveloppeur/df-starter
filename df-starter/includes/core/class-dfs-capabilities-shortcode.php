<?php

if (!defined('ABSPATH')) {
    exit;
}

class DFS_Capabilities_Shortcode
{
    public function register(): void
    {
        add_shortcode('all-capabilities', [$this, 'render']);
    }

    public function render(): string
    {
        $all_caps = [];
        foreach (get_users() as $user) {
            foreach (array_keys($user->allcaps) as $cap) {
                if (!in_array($cap, $all_caps, true)) {
                    $all_caps[] = $cap;
                }
            }
        }

        $out  = '<div class="dfs-capabilities-list">';
        $out .= '<h2>' . esc_html__("All Possible Users' Capabilities", 'df-starter') . '<hr></h2>';
        $out .= '<p>';
        foreach ($all_caps as $cap) {
            $out .= esc_html($cap) . '<br>';
        }
        $out .= '</p></div>';

        return $out;
    }
}
