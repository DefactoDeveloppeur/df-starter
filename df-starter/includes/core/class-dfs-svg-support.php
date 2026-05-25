<?php

if (!defined('ABSPATH')) {
    exit;
}

class DFS_Svg_Support
{
    public function register(): void
    {
        add_filter('upload_mimes', [$this, 'add_svg_mime']);
    }

    public function add_svg_mime(array $mimes): array
    {
        $mimes['svg']  = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
        return $mimes;
    }
}
