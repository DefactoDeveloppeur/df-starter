<?php

if (!defined('ABSPATH')) exit;
class Df_capabilities
{
    public function __construct()
    {
        add_shortcode( 'all-capabilities', [$this,'wpse_all_capabilities'] );
    }

    public function wpse_all_capabilities() {
        $allcaps = array();
        $out = '<style>
        .flex-columns {
            column-count: 3;
            column-gap: 3em;
            column-rule: 1px solid #000;
        }
        h2 {
            text-align: center;
            column-span: all;
        }        
        </style>';
                $out .= '<div class="flex-columns">';
                $out .= "<h2>All Possible Users' Capabilities<hr></h2><p>";
                $users = get_users();
                foreach ( $users as $user ) {
                    $caps = array_keys( $user->allcaps );
                    foreach ( $caps as $cap ) {
                        if ( !in_array( $cap, $allcaps, true ) ) {
                            $num = array_push( $allcaps, $cap );
                        }
                    }
                }
                foreach ( $allcaps as $capability ) {
                    $out .= $capability . '<br>';
                }
                $out .= '</p></div>';
        return $out;
    }

}