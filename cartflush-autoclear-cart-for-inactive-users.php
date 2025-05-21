<?php
/**
 * Plugin Name: CartFlush AutoClear Cart for Inactive Users
 * Description: Automatically clears WooCommerce cart after user inactivity.
 * Version: 1.0.0
 * Author: Rashed Hossain
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CartFlush {

    public function __construct() {
        add_action( 'init', [ $this, 'maybe_clear_cart' ] );
        add_action( 'woocommerce_before_cart', [ $this, 'store_last_activity_time' ] );
        add_action( 'woocommerce_before_checkout_form', [ $this, 'store_last_activity_time' ] );
        add_action( 'woocommerce_add_to_cart', [ $this, 'store_last_activity_time' ] );
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function store_last_activity_time() {
        WC()->session->set( 'cart_last_activity', time() );
    }

    public function maybe_clear_cart() {
        if ( is_admin() || ! is_user_logged_in() && ! WC()->session ) return;

        $last_activity = WC()->session->get( 'cart_last_activity' );
        $expire_minutes = get_option( 'cartflush_expiration_time', 30 );

        if ( $last_activity && ( time() - $last_activity ) > ( $expire_minutes * 60 ) ) {
            WC()->cart->empty_cart();
            WC()->session->set( 'cart_last_activity', time() );
            add_action( 'woocommerce_before_cart', function() {
                wc_print_notice( 'Your cart was cleared due to inactivity.', 'notice' );
            });
        }
    }

    public function add_settings_page() {
        add_options_page( 'CartFlush Settings', 'CartFlush', 'manage_options', 'cartflush-settings', [ $this, 'settings_page_html' ] );
    }

    public function register_settings() {
        register_setting( 'cartflush_settings_group', 'cartflush_expiration_time', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 30,
        ]);

        add_settings_section( 'cartflush_main', 'Main Settings', null, 'cartflush-settings' );

        add_settings_field(
            'cartflush_expiration_time',
            'Cart Expiration Time (in minutes)',
            function() {
                $value = get_option( 'cartflush_expiration_time', 30 );
                echo '<input type="number" name="cartflush_expiration_time" value="' . esc_attr( $value ) . '" min="1">';
            },
            'cartflush-settings',
            'cartflush_main'
        );
    }

    public function settings_page_html() {
        echo '<div class="wrap">
                <h1>CartFlush Settings</h1>
                <form method="post" action="options.php">';
        settings_fields( 'cartflush_settings_group' );
        do_settings_sections( 'cartflush-settings' );
        submit_button();
        echo '</form></div>';
    }
}

new CartFlush();