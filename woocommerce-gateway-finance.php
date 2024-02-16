<?php
/**
 * @package Finance Gateway
 * @author Divido <support@divido.com>
 * @copyright 2023 Divido Financial Services
 * @license MIT
 * 
 * Plugin Name: Finance Payment Gateway for WooCommerce
 * Plugin URI: http://integrations.divido.com/finance-gateway-woocommerce
 * Description: The Finance Payment Gateway plugin for WooCommerce.
 * Version: 2.8.0
 *
 * Author: Divido Financial Services Ltd
 * Author URI: www.divido.com
 * Text Domain: woocommerce-finance-gateway
 * Domain Path: /i18n/languages/
 * WC tested up to: 8.5.2
 *
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WC Finance Payment gateway plugin class.
 *
 * @class WC_Finance_Payments
 */
class WC_Finance_Payments {

    /**
     * Plugin bootstrapping.
     */
    public static function init() {

        // Finance Payments gateway class.
        add_action( 'plugins_loaded', array( __CLASS__, 'includes' ), 0 );

        // Make the Finance Payments gateway available to WC.
        add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'add_gateway' ) );

        // Registers WooCommerce Blocks integration.
        add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'block_support' ) );

        // Confirms High-Performance Order Storage support
        add_action( 'before_woocommerce_init', array(__CLASS__, 'hpos_support'));

    }

    /**
     * Add the Finance Payment gateway to the list of available gateways.
     *
     * @param array
     */
    public static function add_gateway( $gateways ) {

        $options = get_option( 'woocommerce_finance_settings', array() );

        if ( isset( $options['hide_for_non_admin_users'] ) ) {
            $hide_for_non_admin_users = $options['hide_for_non_admin_users'];
        } else {
            $hide_for_non_admin_users = 'no';
        }

        if ( ( 'yes' === $hide_for_non_admin_users && current_user_can( 'manage_options' ) ) || 'no' === $hide_for_non_admin_users ) {
            $gateways[] = 'WC_Gateway_Finance';
        }
        return $gateways;
    }

    /**
     * Plugin includes.
     */
    public static function includes() {

        // Make the WC_Gateway_Finance class available.
        if ( class_exists( 'WC_Payment_Gateway' ) ) {
            require_once 'includes/class-wc-gateway-finance.php';
        }
    }

    /**
     * Plugin url.
     *
     * @return string
     */
    public static function plugin_url() {
        return untrailingslashit( plugins_url( '/', __FILE__ ) );
    }

    public static function plugin_base_basename() {
        return plugin_basename(__FILE__);
    }

    /**
     * Plugin url.
     *
     * @return string
     */
    public static function plugin_abspath() {
        return trailingslashit( plugin_dir_path( __FILE__ ) );
    }

    /**
     * Registers WooCommerce Blocks integration.
     *
     */
    public static function block_support() {
        if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
            require_once 'includes/blocks/class-wc-finance-payments-blocks.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                    $payment_method_registry->register( new WC_Gateway_Finance_Blocks_Support() );
                }
            );
        }
    }

    public static function hpos_support(){
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    }

    /**
     * Add plugin action links.
     *
     * Add a link to the settings page on the plugins.php page.
     *
     * @since 2.0.2
     *
     * @param  array $links List of existing plugin action links.
     * @return array         List of modified plugin action links.
     */
    public function finance_gateway_settings_link($links)
    {
        $_link = '<a href="' . esc_url(admin_url('/admin.php?page=wc-settings&tab=checkout&section=finance')) . '">' . __('backendsettings_label', 'woocommerce-finance-gateway') . '</a>';
        $links[] = $_link;

        return $links;
    }
}

WC_Finance_Payments::init();