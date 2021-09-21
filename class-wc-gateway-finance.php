<?php

use Divido\MerchantSDK\Environment;
use Divido\MerchantSDK\Exceptions\InvalidApiKeyFormatException;
use Divido\MerchantSDK\Exceptions\InvalidEnvironmentException;

defined('ABSPATH') or die('Denied');
/**
 *  Finance Gateway for Woocommerce
 *
 * @package Finance Gateway
 * @author Divido <support@divido.com>
 * @copyright 2019 Divido Financial Services
 * @license MIT
 *
 * Plugin Name: Finance Payment Gateway for WooCommerce
 * Plugin URI: http://integrations.divido.com/finance-gateway-woocommerce
 * Description: The Finance Payment Gateway plugin for WooCommerce.
 * Version: 2.3.2
 *
 * Author: Divido Financial Services Ltd
 * Author URI: www.divido.com
 * Text Domain: woocommerce-finance-gateway
 * Domain Path: /i18n/languages/
 * WC tested up to: 4.7.1
 */

/**
 * Load the woocommerce plugin.
 */
add_action('plugins_loaded', 'woocommerce_finance_init', 0);

/**
 * Inititalize script for finance plugin.
 *
 * @return void
 */
function woocommerce_finance_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    include_once WP_PLUGIN_DIR . '/' . plugin_basename(dirname(__FILE__)) . '/vendor/autoload.php';

    /**
     * Merchant SDK class
     *
     * Constructs an instance of the merchant sdk to be used
     **/
    class Merchant_SDK
    {
        /**
         * Creates and returns a merchant sdk instance
         *
         * @param string The merchant api url
         * @param string The api key for the environment
         *
         * @return Divido\MerchantSDK\Client|null The Merchant SDK client instance
         */
        public static function getSDK($url, $api_key)
        {
            try{
                $env = Environment::getEnvironmentFromAPIKey($api_key);
            }catch (InvalidApiKeyFormatException $e){
                return null;
            }catch (InvalidEnvironmentException $e){
                return null;
            }

            $client = new \GuzzleHttp\Client();
            $httpClientWrapper = new \Divido\MerchantSDK\HttpClient\HttpClientWrapper(
                new \Divido\MerchantSDKGuzzle6\GuzzleAdapter($client),
                $url,
                $api_key
            );

            return new \Divido\MerchantSDK\Client($httpClientWrapper, $env);
        }
    }

    /**
     * Finance Payment Gateway class
     **/
    class WC_Gateway_Finance extends WC_Payment_Gateway
    {
        /**
         * Available countries
         *
         * @var array $avaiable_countries A hardcoded array of countries.
         */
        public $avaiable_countries = array('GB', 'SE', 'NO', 'DK', 'ES', 'FI', 'DE', 'FR', 'PE', 'CO', 'BR');

        /**
         * Api Key
         *
         * @var string $api_key The Finance Api Key.
         */
        public $api_key;

        function wpdocs_load_textdomain()
        {
            if (!load_plugin_textdomain(
                'woocommerce-finance-gateway',
                false,
                dirname(plugin_basename(__FILE__)) . '/i18n/languages'
            )) {
                $locale = determine_locale();
                $split = explode("_", $locale, 1);
                $iso = $split[0];
                $dumb_locale = "{$iso}_" . strtoupper($iso);
                if (!load_textdomain(
                    'woocommerce-finance-gateway',
                    WP_PLUGIN_DIR . '/' . dirname(plugin_basename(__FILE__)) . "/i18n/languages/woocommerce-finance-gateway-{$dumb_locale}.mo"
                )) {
                    load_textdomain(
                        'woocommerce-finance-gateway',
                        WP_PLUGIN_DIR . '/' . dirname(plugin_basename(__FILE__)) . '/i18n/languages/woocommerce-finance-gateway-en_GB.mo'
                    );
                }
            }
        }

        /**
         * Plugin Class Constructor
         *
         * Initialise the finance plugin.
         *
         * @return void
         */
        function __construct()
        {
            $this->plugin_version = '2.3.2';
            add_action('init', array($this, 'wpdocs_load_textdomain'));

            $this->id = 'finance';
            $this->method_title = __('globalplugin_title', 'woocommerce-finance-gateway');
            $this->method_description = __('globalplugin_description', 'woocommerce-finance-gateway');
            $this->has_fields = true;
            // Load the settings.
            $this->init_settings();
            // Get setting values.
            $this->title = (!empty($this->settings['title'])) ? $this->settings['title'] : __('frontend/checkoutcheckout_title_default', 'woocommerce-finance-gateway');
            $this->description = (!empty($this->settings['description'])) ? $this->settings['description'] : __('frontend/checkoutcheckout_description_default', 'woocommerce-finance-gateway');
            $this->calculator_theme = (!empty($this->settings['calculatorTheme'])) ? $this->settings['calculatorTheme'] : 'enabled';
            $this->show_widget = (!empty($this->settings['showWidget'])) ? $this->settings['showWidget'] : true;
            $this->enabled = (!empty($this->settings['enabled'])) ? $this->settings['enabled'] : false;
            $this->api_key = (!empty($this->settings['apiKey'])) ? $this->settings['apiKey'] : '';
            $this->footnote = (!empty($this->settings['footnote'])) ? $this->settings['footnote'] : ' ';
            $this->buttonText = (!empty($this->settings['buttonText'])) ? $this->settings['buttonText'] : ' ';
            $this->cart_threshold = (!empty($this->settings['cartThreshold'])) ? $this->settings['cartThreshold'] : 250;
            $this->max_loan_amount = (!empty($this->settings['maxLoanAmount'])) ? $this->settings['maxLoanAmount'] : 25000;
            $this->auto_fulfillment = (!empty($this->settings['autoFulfillment'])) ? $this->settings['autoFulfillment'] : "yes";
            $this->auto_refund = (!empty($this->settings['autoRefund'])) ? $this->settings['autoRefund'] : "yes";
            $this->auto_cancel = (!empty($this->settings['autoCancel'])) ? $this->settings['autoCancel'] : "yes";
            $this->widget_threshold = (!empty($this->settings['widgetThreshold'])) ? $this->settings['widgetThreshold'] : 250;
            $this->secret = (!empty($this->settings['secret'])) ? $this->settings['secret'] : '';
            $this->product_select = (!empty($this->settings['productSelect'])) ? $this->settings['productSelect'] : '';
            $this->useStoreLanguage = (!empty($this->settings['useStoreLanguage'])) ? $this->settings['useStoreLanguage'] : '';
            // set the environment from the api key
            try {
                $this->environment = Environment::getEnvironmentFromAPIKey($this->api_key);
            } catch (Exception $e) {
                $this->environment = '';
            }
            // set the tenancy environment based on the user input "url" field or default it from the api key
            $this->url = (!empty($this->settings['url'])) ? $this->settings['url'] : $this->get_default_merchant_api_pub_url($this->api_key);

            add_filter('woocommerce_gateway_icon', array($this, 'custom_gateway_icon'), 10, 2);

            // Load logger.
            if (version_compare(WC_VERSION, '2.7', '<')) {
                $this->logger = new WC_Logger();
            } else {
                $this->logger = wc_get_logger();
            }

            if (is_admin()) {
                // Load the form fields.
                $this->init_form_fields();
            }
            $this->woo_version = $this->get_woo_version();

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options')); // Version 2.0 Hook.
            // product settings.
            add_action('woocommerce_product_write_panel_tabs', array($this, 'product_write_panel_tab'));
            if (version_compare(WC_VERSION, '2.7', '<')) {
                add_action('woocommerce_product_write_panels', array($this, 'product_write_panel'));
            } else {
                add_action('woocommerce_product_data_panels', array($this, 'product_write_panel'));
            }
            add_action('woocommerce_process_product_meta', array($this, 'product_save_data'), 10, 2);
            // product page.

            if ('disabled' !== $this->show_widget) {
                if ('disabled' !== $this->calculator_theme) {
                    add_action('woocommerce_after_single_product_summary', array($this, 'product_calculator'));
                } else {
                    add_action('woocommerce_single_product_summary', array($this, 'product_widget'), 15);
                }
            }
            // order admin page (making sure it only adds once).
            global $finances_set_admin_order_display;
            if (!isset($finances_set_admin_order_display)) {
                add_action('woocommerce_admin_order_data_after_order_details', array($this, 'display_order_data_in_admin'));
                $finances_set_admin_order_display = true;
            }
            // checkout.
            add_filter('woocommerce_payment_gateways', array($this, 'add_method'));
            // ajax callback.
            add_action('wp_ajax_nopriv_woocommerce_finance_callback', array($this, 'callback'));
            add_action('wp_ajax_woocommerce_finance_callback', array($this, 'callback'));
            add_action('wp_head', array($this, 'add_api_to_head'));
            add_action('woocommerce_order_status_completed', array($this, 'send_finance_fulfillment_request'), 10, 1);
            add_action('woocommerce_order_status_refunded', array($this, 'send_refund_request'), 10, 1);
            add_action('woocommerce_order_status_cancelled', array($this, 'send_cancellation_request'), 10, 1);

            // scripts.
            add_action('wp_enqueue_scripts', array($this, 'enqueue'));
            add_action('admin_enqueue_scripts', array($this, 'wpdocs_enqueue_custom_admin_style'));
            //Since 1.0.2
            add_shortcode('finance_widget', array($this, 'anypage_widget'));
            //Since 1.0.3
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'finance_gateway_settings_link'));
        }

        /**
         * @param $icon
         * @param $id
         * @return string
         */
        public function custom_gateway_icon($icon, $id)
        {
            if ($id === 'finance') {
                if (empty($this->api_key)) {
                    return "<img style='float:right;' src='https://cdn.divido.com/widget/themes/divido/logo.png'/>";
                } else if ($this->get_finance_env() === 'nordea') {
                    return "<img style='height:24px;float:right;' src='https://cdn.divido.com/widget/themes/" . $this->get_finance_env() . "/logo.png'/>";
                } else {
                    return "<img style='float:right;' src='https://cdn.divido.com/widget/themes/" . $this->get_finance_env() . "/logo.png'/>";
                }
            } else {
                return $icon;
            }
        }

        /**
         * Anypage Widget
         *
         * A helper for the shortcode widget
         *
         * @since 1.0.2
         *
         * @param  array $atts Optional Attributes array.
         * @return mixed
         */
        public function anypage_widget($atts)
        {
            if ('yes' !== $this->enabled || '' === $this->api_key) {
                return false;
            }
            $finance = $this->get_finance_env();
            if ($this->environment === 'production') {
                wp_register_script('woocommerce-finance-gateway-calculator', '//cdn.divido.com/widget/v3/' . $finance . '.calculator.js', false, 1.0, true);
            } else {
                wp_register_script('woocommerce-finance-gateway-calculator', '//cdn.divido.com/widget/v3/' . $finance . '.' . $this->environment . '.calculator.js', false, 1.0, true);
            }
            wp_enqueue_script('woocommerce-finance-gateway-calculator');

            $attributes = shortcode_atts(array(
                'amount' => '250',
                'mode' => 'lightbox',
                'buttonText' => '',
                'plans' => '',
                'footnote' => ''
            ), $atts, 'finance_widget');

            if (is_array($atts)) {
                foreach ($atts as $key => $value) {
                    $attributes[$key] = $value;
                }
            }

            $mode = 'data-mode="lightbox"';
            if ($attributes['mode'] != 'lightbox') {
                $mode = ' data-mode="calculator"';
            }

            $plans = '';
            if ($attributes["plans"] != '') {
                $plans = ' data-plans="' . $attributes["plans"] . '"';
            }

            $buttonText = '';
            if ($attributes["buttonText"] != '') {
                $buttonText = ' data-button-text="' . $attributes["buttonText"] . '"';
            }
            $footnote = '';
            if ($attributes["footnote"] != '') {
                $footnote = ' data-footnote="' . $attributes["footnote"] . '"';
            }

            return '<div data-calculator-widget ' . $mode . ' data-amount="' . esc_attr($attributes["amount"]) . '" ' . $buttonText . ' ' . $footnote . ' ' . $plans . ' ></div>';
        }

        /**
         * Get  Finances Wrapper
         *
         * Calls Finance endpoint to return all finances for merchant
         *
         * @since 1.0.0
         *
         * @return array
         */
        function get_all_finances()
        {
            $finances = false;
            $transient_name = 'finances';
            $finances = get_transient($transient_name);
            $apiKey = get_transient("api_key");

            // only fetch new finances if the api key is different
            // OR API key transisent is not set
            // OR finances transient is not set
            if ($apiKey !== $this->api_key || empty($apiKey) || empty($finances)) {

                $request_options = (new \Divido\MerchantSDK\Handlers\ApiRequestOptions());
                // Retrieve all finance plans for the merchant.
                try {
                    $sdk = Merchant_SDK::getSDK($this->url, $this->api_key);

                    if($sdk === null){
                        return [];
                    }

                    $plans = $sdk->getAllPlans($request_options);
                    $plans = $plans->getResources();
                    set_transient($transient_name, $plans, 60 * 60 * 1);
                    set_transient("api_key", $this->api_key);

                    return $plans;
                } catch (Exception $e) {
                    return [];
                }
            } else {
                return $finances;
            }
        }

        /**
         * Enque Add Finance styles and scripts
         *
         * @since 1.0.0
         *
         * @return void
         */
        function enqueue()
        {
            if ($this->api_key && is_product() || $this->api_key && is_checkout()) {
                $key = preg_split('/\./', $this->api_key);
                $protocol = (isset($_SERVER['HTTPS']) && 'on' === $_SERVER['HTTPS']) ? 'https' : 'http'; // Input var okay.
                $finance = $this->get_finance_env();
                if ($this->environment === 'production') {
                    wp_register_script('woocommerce-finance-gateway-calculator', $protocol . '://cdn.divido.com/widget/v3/' . $finance . '.calculator.js', false, 1.0, true);
                } else {
                    wp_register_script('woocommerce-finance-gateway-calculator', $protocol . '://cdn.divido.com/widget/v3/' . $finance . '.' . $this->environment . '.calculator.js', false, 1.0, true);
                }
                wp_register_script('woocoomerce-finance-gateway-calculator_price_update', plugins_url('', __FILE__) . '/js/widget_price_update.js', false, 1.0, true);
                wp_register_style('woocommerce-finance-gateway-style', plugins_url('', __FILE__) . '/css/style.css', false, 1.0);
                $array = array(
                    'environment' => __($finance)
                );
                wp_localize_script('woocoomerce-finance-gateway-calculator_price_update', 'environment', $array);
            }
            wp_enqueue_style('woocommerce-finance-gateway-style');
            wp_enqueue_script('woocommerce-finance-gateway-calculator');
            wp_enqueue_script('woocoomerce-finance-gateway-calculator_price_update');
        }

        /**
         * Add Finance Javascript
         *
         * We need to add some specific js to the head of the page to ensure the element reloads
         *
         * @since 1.0.0
         *
         * @return void
         */
        function add_api_to_head()
        {
            if ($this->api_key) {
                $key = preg_split('/\./', $this->api_key);

?>
<script type='text/javascript'>
window.__widgetConfig = {
    apiKey: '<?php echo esc_attr(strtolower($key[0])); ?>'

};

var <?php echo ($this->get_finance_env()) ?>Key = '<?php echo esc_attr(strtolower($key[0])); ?>'
</script>
<script>
// <![CDATA[
function waitForElementToDisplay(selector, time) {
    if (document.querySelector(selector) !== null) {
        __widgetInstance.init()
        return;
    } else {
        setTimeout(function() {
            waitForElementToDisplay(selector, time);
        }, time);
    }
}

jQuery(document).ready(function() {
    waitForElementToDisplay('#financeWidget', 1000);
});

// ]]>
</script>

<?php
            }
        }

        /**
         * Helper function to display save data in Admin
         *
         * Display the extra data in the order admin panel.
         *
         * @since 1.0.0
         *
         * @param  [object] $order The Order view.
         * @return void
         */
        function display_order_data_in_admin($order)
        {
            $ref_and_finance = $this->get_ref_finance($order);
            if ($ref_and_finance['ref']) {
                echo '<p class="form-field form-field-wide"><strong>' . esc_attr(__('backend/orderfinance_reference_number_label', 'woocommerce-finance-gateway')) . ':</strong><br />' . esc_html($ref_and_finance['ref']) . '</p>';
            }
            if ($ref_and_finance['finance']) {
                echo '<p class="form-field form-field-wide"><strong>' . esc_attr(__('backend/orderfinance_plan_id_label', 'woocommerce-finance-gateway')) . ':</strong><br />' . esc_html($ref_and_finance['finance']) . '</p>';
            }
        }

        /**
         * Callback The callback function listens to calls from Finance
         *
         * @since 1.0.0
         *
         * @return void
         */
        function callback()
        {
            if (isset($_SERVER['HTTP_RAW_POST_DATA']) && wp_unslash($_SERVER['HTTP_RAW_POST_DATA'])) { // Input var okay.
                $data = file_get_contents(wp_unslash($_SERVER['HTTP_RAW_POST_DATA'])); // Input var okay.
            } else {
                $data = file_get_contents('php://input');
            }
            // If secret is set, check against http header.

            if ('' !== $this->secret) {
                $callback_sign = isset($_SERVER['HTTP_X_DIVIDO_HMAC_SHA256']) ? $_SERVER['HTTP_X_DIVIDO_HMAC_SHA256'] : ''; // Input var okay.
                $sign = $this->create_signature($data, $this->secret);
                if ($callback_sign !== $sign) {
                    $this->logger->debug('FINANCE', 'ERROR: Hash error');
                    $data_json = json_decode($data);
                    if (is_object($data_json)) {
                        if ($data_json->metadata->order_number) {
                            $order = new WC_Order($data_json->metadata->order_number);
                            $order->add_order_note(__('backend/ordershared_secret_error_msg', 'woocommerce-finance-gateway'));
                            $this->send_json('error', "Invalid Hash error");
                        }
                    }

                    return;
                }
            }
            // Use $data as JSON object.
            $data_json = json_decode($data);
            if (is_object($data_json)) {
                if ($data_json->metadata->order_number) {
                    $finance_reference = get_post_meta($data_json->metadata->order_number, '_finance_reference');
                    if (isset($finance_reference[0]) && $finance_reference[0] === $data_json->proposal) {
                        $order = new WC_Order($data_json->metadata->order_number);
                        $finance_amount = get_post_meta($data_json->metadata->order_number, '_finance_amount');
                        // Check if the requested amount matched order amount.
                        if ($finance_amount[0] !== $order->get_total()) {
                            // Amount mismatch, hold.
                            $order->update_status('on-hold');
                            $order->add_order_note(__('backend/orderorder_amount_error_msg', 'woocommerce-finance-gateway'));
                            $this->logger->debug('Finance', 'ERROR: The requested credit of £' . $finance_amount[0] . ' did not match order sum, putting order on hold. Status: ' . $data_json->status . ' Order: ' . $data_json->metadata->order_number . ' Finance Reference: ' . $finance_reference[0]);
                            $this->send_json();
                        } else {
                            // Amount matches, update status.

                            if ('DECLINED' === $data_json->status) {
                                $order->update_status('failed');
                                $this->send_json();
                            } elseif ('SIGNED' === $data_json->status) {
                                $this->logger->error('Finance', 'processing');
                                $order->update_status('processing', $data_json->application);
                                $this->send_json();
                            } elseif ('READY' === $data_json->status) {
                                $order->add_order_note('Finance status: ' . $data_json->status);
                                $order->payment_complete();
                                $this->send_json();
                            }
                        }
                        // Log status to order.
                        $order->add_order_note(__('backend/orderorder_status_label', 'woocommerce-finance-gateway') . ': ' . $data_json->status);
                        $this->logger->debug('Finance', 'STATUS UPDATE: ' . $data_json->status . ' Order: ' . $data_json->metadata->order_number . ' Finance Reference: ' . $finance_reference[0]);
                    }
                }
            }
        }

        /**
         * Add Finance payment methods using filter woocommerce_payment_gateways
         *
         * @since 1.0.0
         *
         * @param  array $methods Array of payment methods.
         * @return array
         */
        public function add_method($methods)
        {
            if (is_admin()) {
                $methods[] = 'WC_Gateway_Finance';
            } else {
                $is_available = $this->is_available();
                if ($is_available) {
                    $methods[] = 'WC_Gateway_Finance';
                }
            }

            return $methods;
        }

        /**
         * Provides a way to support both 2.6 and 2.7 since get_price_including_tax
         * gets deprecated in 2.7, and wc_get_price_including_tax gets introduced in
         * 2.7.
         *
         * Returns a float representing the price of this product in pence/pennies
         *
         * @since  1.0.0
         * @param  WC_Product $product Product instance.
         * @param  array $args Args array.
         * @return float
         */
        private function get_price_including_tax($product, $args)
        {
            if (version_compare(WC_VERSION, '2.7', '<')) {
                $args = wp_parse_args(
                    $args,
                    array(
                        'qty' => '1',
                        'price' => '',
                    )
                );

                $priceIncludingTax = $product->get_price_including_tax($args['qty'], $args['price']);
            } else {
                $priceIncludingTax = wc_get_price_including_tax($product, $args);
            }

            // $priceIncludingTax could be a float or string
            // Before we do math on this we need to convert the value to a float
            if (!is_float($priceIncludingTax)) {
                $priceIncludingTax = floatval($priceIncludingTax);
            }

            // Multiply by 100 to get the pence/cents value
            $priceIncludingTax = $priceIncludingTax * 100;

            return $priceIncludingTax;
        }

        /**
         * Check if this gateway is enabled and available in the user's country.
         *
         * @since 1.0.0
         *
         * @param  boolean $product Product Instace.
         * @return float
         */
        public function is_available($product = false)
        {

            if ('yes' !== $this->enabled || '' === $this->api_key) {
                return false;
            }

            if (is_checkout()) {
                $checkout_finance_options = $this->get_checkout_plans();
                if (!$checkout_finance_options) {
                    return false;
                }
            }

            if (is_object($product)) {
                if (version_compare($this->woo_version, '3.0.0') >= 0) {
                    $data = maybe_unserialize(get_post_meta($product->get_id(), 'woo_finance_product_tab', true));
                } else {
                    $data = maybe_unserialize(get_post_meta($product->id, 'woo_finance_product_tab', true));
                }
                if (isset($data[0]) && is_array($data[0]) && isset($data[0]['active']) && 'selected' === $data[0]['active']) {
                    if (is_array($data[0]['finances']) && count($data[0]['finances']) > 0) {
                        return true;
                    } else {
                        return false;
                    }
                } elseif ('price' === $this->settings['productSelect']) {
                    $limit = $this->settings['priceSelection'];
                    if ($this->get_price_including_tax($product, '') > 0 && $this->get_price_including_tax($product, '') >= $limit) {
                        return true;
                    } else {
                        return false;
                    }
                } elseif ('selection' === $this->settings['productSelect']) {
                    return false;
                } elseif ('all' === $this->settings['productSelect']) {
                    return true;
                }

                return false;
            }
            // In Cart.
            global $woocommerce;
            $settings = $this->settings;
            $threshold = $this->cart_threshold;
            $upperLimit = $this->max_loan_amount;
            $cart = $woocommerce->cart;
            if (empty($cart)) {
                return false;
            }
            if ($threshold > $cart->total) {
                return false;
            }
            if ($upperLimit < $cart->total) {
                return false;
            }
            if ('all' === $settings['productSelect']) {
                return true;
            }
            if ('price' === $settings['productSelect']) {
                if ($cart->subtotal < $settings['priceSelection']) {
                    return false;
                }
            }

            return true;
        }

        /**
         * Get any finance options set for the checkout
         *
         * @since 1.0.0
         *
         * @return array|false
         */
        public function get_finance_options()
        {
            global $woocommerce;
            if ('yes' !== $this->enabled) {
                return false;
            }
            $finance_options = array();
            if (is_checkout() || is_product()) {
                foreach ($woocommerce->cart->get_cart() as $item) {
                    $product = $item['data'];
                    $finances = $this->get_product_finance_options($product);
                    if (!$finances && !is_array($finances)) {
                        return false;
                    }
                    foreach ($finances as $finance) {
                        $finance_options[$finance] = $finance;
                    }
                }
            }

            return (count($finance_options) > 0) ? $finance_options : array();
        }

        /**
         * Get Product specific finance options.
         *
         * @since 1.0.0
         *
         * @param  object $product Product Instance.
         * @return array|false
         */
        public function get_product_finance_options($product)
        {
            if ('yes' !== $this->enabled) {
                return false;
            }
            if (version_compare($this->woo_version, '3.0.0') >= 0) {
                if ($product->get_type() === 'variation') {
                    $data = maybe_unserialize(get_post_meta($product->get_parent_id(), 'woo_finance_product_tab', true));
                } else {
                    $data = maybe_unserialize(get_post_meta($product->get_id(), 'woo_finance_product_tab', true));
                }
            } else {
                $data = maybe_unserialize(get_post_meta($product->id, 'woo_finance_product_tab', true));
            }
            if (isset($data[0]) && is_array($data[0]) && isset($data[0]['active']) && 'selected' === $data[0]['active']) {
                $finances = array();

                return (is_array($data[0]['finances']) && count($data[0]['finances']) > 0) ? $data[0]['finances'] : array();
            } elseif ('selection' === $this->settings['showFinanceOptions']) {
                return $this->settings['showFinanceOptionSelection'];
            } elseif ('all' === $this->settings['showFinanceOptions']) {
                return false;
            }

            return false;
        }

        /**
         * Get Checkout specific finance plans.
         *
         * @since 1.0.0
         *
         * @return string|false
         */
        public function get_checkout_plans()
        {
            $finances = $this->get_finances($this->get_finance_options());
            if (is_array($finances)) {
                $plans = array_keys($finances);
            }

            return (is_array($plans)) ? implode(',', $plans) : false;
        }

        /**
         * Get specific product plans.
         *
         * @since 1.0.0
         *
         * @param  object $product WC product instance.
         * @return string|false
         */
        public function get_product_plans($product)
        {
            $finances = $this->get_product_finance_options($product);

            return (is_array($finances)) ? implode(',', $finances) : false;
        }

        /**
         * Product calculator helper.
         *
         * @param  object $product The current product.
         * @return void
         */
        public function product_calculator($product)
        {
            global $product;
            if ($this->is_available($product)) {
                $environment = $this->get_finance_env();
                $plans = $this->get_product_plans($product);
                $price = $this->get_price_including_tax($product, '');
                $language = '';
                if ($this->useStoreLanguage === "yes") {
                    $language = 'data-language="' . $this->get_language() . '" ';
                }
                include_once WP_PLUGIN_DIR . '/' . plugin_basename(dirname(__FILE__)) . '/includes/calculator.php';
            }
        }

        /**
         * Product widget helper.
         *
         * @since 1.0.0
         *
         * @param  object $product The current product.
         * @return void
         */
        public function product_widget($product)
        {
            global $product;
            if ($this->api_key) {
                $price = $this->get_price_including_tax($product, '');
                $plans = $this->get_product_plans($product);
                $environment = $this->get_finance_env();
                if ($this->is_available($product) && $price > ($this->widget_threshold * 100)) {
                    $button_text = '';
                    if (!empty(sanitize_text_field($this->buttonText))) {
                        $button_text = 'data-button-text="' . sanitize_text_field($this->buttonText) . '" ';
                    }

                    $footnote = '';
                    if (!empty(sanitize_text_field($this->footnote))) {
                        $footnote = 'data-footnote="' . sanitize_text_field($this->footnote) . '" ';
                    }

                    $plans = $this->get_product_plans($product);

                    $language = '';
                    if ($this->useStoreLanguage === "yes") {
                        $language = 'data-language="' . $this->get_language() . '" ';
                    }

                    include_once WP_PLUGIN_DIR . '/' . plugin_basename(dirname(__FILE__)) . '/includes/widget.php';
                }
            }
        }

        /**
         * Function to add finance product into admin view this add tabs
         *
         * @since 1.0.0
         *
         * @return false
         */
        public function product_write_panel_tab()
        {
            if ('yes' !== $this->enabled) {
                return false;
            }
            $environment = $this->get_finance_env();
            $tab_icon = 'https://s3-eu-west-1.amazonaws.com/content.divido.com/plugins/powered-by-divido/' . $environment . '/woocommerce/images/finance-icon.png';

            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0') >= 0) {
                $style = 'content:"";padding:5px 5px 5px 22px; background-image:url(' . $tab_icon . '); background-repeat:no-repeat;background-size: 15px 15px;background-position:8px 8px;';
                $active_style = '';
            } else {
                $style = 'content:"";padding:5px 5px 5px 22px; line-height:16px; border-bottom:1px solid #d5d5d5; text-shadow:0 1px 1px #fff; color:#555555;background-size: 15px 15px; background-image:url(' . $tab_icon . '); background-repeat:no-repeat; background-position:8px 8px;';
                $active_style = '#woocommerce-product-data ul.product_data_tabs li.my_plugin_tab.active a { border-bottom: 1px solid #F8F8F8; }';
            }
            ?>
<style type="text/css">
#woocommerce-product-data ul.product_data_tabs li.finance_tab a {
    <?php echo esc_attr($style);
    ?>
}

#woocommerce-product-data ul.product_data_tabs li.finance_tab a:before {
    content: '' !important;
}

<?php echo esc_attr($active_style);
?>
</style>
<?php

            echo '<li class="finance_tab"><a href="#finance_tab"><span>' . esc_attr(__('globalplugin_title', 'woocommerce-finance-gateway')) . '</span></a></li>';
        }

        /**
         * Function to add the product panel
         *
         * @since 1.0.0
         *
         * @return false
         */
        public function product_write_panel()
        {
            if ('yes' !== $this->enabled) {
                return false;
            }
            global $post;
            $tab_data = maybe_unserialize(get_post_meta($post->ID, 'woo_finance_product_tab', true));
            if (empty($tab_data)) {
                $tab_data = array();
                $tab_data[] = array(
                    'active' => 'default',
                    'finances' => array(),
                );
            }
            if (empty($tab_data[0]['finances'])) {
                $tab_data[0]['finances'] = array();
            }
            $finances = $this->get_finances();

        ?>
<div id="finance_tab" class="panel woocommerce_options_panel">
    <p class="form-field _hide_title_field ">
        <label
            for="_available"><?php esc_html_e('frontend/productavailable_on_finance_label', 'woocommerce-finance-gateway'); ?></label>

        <input type="radio" class="checkbox" name="_tab_finance_active" id="finance_active_default" value="default"
            <?php print ('default' === $tab_data[0]['active']) ? 'checked' : ''; ?>>
        <?php esc_html_e('frontend/productdefault_settings_label', 'woocommerce-finance-gateway'); ?>
        <br style="clear:both;" />
        <input type="radio" class="checkbox" name="_tab_finance_active" id="finance_active_selected" value="selected"
            <?php print ('selected' === $tab_data[0]['active']) ? 'checked' : ''; ?>>
        <?php esc_html_e('frontend/productselected_plans_label', 'woocommerce-finance-gateway'); ?>
        <br style="clear:both;" />
    </p>
    <p class="form-field _hide_title_field" id="selectedFinance" style="display:none;">
        <label
            for="_hide_title"><?php esc_html_e('frontend/productselected_plans_label', 'woocommerce-finance-gateway'); ?></label>

        <?php

                    foreach ($finances as $finance => $value) {

                    ?>
        <input type="checkbox" class="checkbox" name="_tab_finances[]" id="finances_<?php print esc_attr($finance); ?>"
            value="<?php print esc_attr($finance); ?>"
            <?php print (in_array($finance, $tab_data[0]['finances'], true)) ? 'checked' : ''; ?>>
        &nbsp;<?php print esc_attr($value['description']); ?>
        <br style="clear:both;" />
        <?php } ?>
    </p>
</div>
<script type="text/javascript">
function checkActive() {
    jQuery("#selectedFinance").hide();
    if (jQuery("input[name=_tab_finance_active]:checked").val() === 'selected') {
        jQuery("#selectedFinance").show();
    }
}

jQuery(document).ready(function() {
    checkActive();
});
jQuery("input[name=_tab_finance_active]").change(function() {
    checkActive();
});
</script>
<?php
        }

        /**
         * A function to save metadata per product
         *
         * @since 1.0.0
         *
         * @param  [type] $post_id The product Post Id.
         * @param  [type] $post    The Post.
         * @return void
         */
        public function product_save_data($post_id, $post)
        {
            $active = isset($_POST['_tab_finance_active']) ? sanitize_text_field(wp_unslash($_POST['_tab_finance_active'])) : ''; // Input var okay.
            $finances = isset($_POST['_tab_finances']) ? wp_unslash($_POST['_tab_finances']) : ''; // Input var okay.
            if ((empty($active) || 'default' === $active) && get_post_meta($post_id, 'woo_finance_product_tab', true)) {
                delete_post_meta($post_id, 'woo_finance_product_tab');
            } else {
                $tab_data = array();
                $tab_title = isset($tab_title) ? $tab_title : '';
                $tab_id = '';
                // convert the tab title into an id string.
                $tab_id = strtolower($tab_title);
                $tab_id = preg_replace('/[^\w\s]/', '', $tab_id); // remove non-alphas, numbers, underscores or whitespace.
                $tab_id = preg_replace('/_+/', ' ', $tab_id); // replace all underscores with single spaces.
                $tab_id = preg_replace('/\s+/', '-', $tab_id); // replace all multiple spaces with single dashes.
                $tab_id = 'tab-' . $tab_id; // prepend with 'tab-' string.
                // save the data to the database.
                $tab_data[] = array(
                    'active' => $active,
                    'finances' => $finances,
                    'id' => $tab_id,
                );
                update_post_meta($post_id, 'woo_finance_product_tab', $tab_data);
            }
        }

        /**
         * Initialize Gateway Settings Form Fields.
         *
         * @since 1.0.0
         *
         */
        function init_form_fields()
        {
            $this->init_settings();
            $this->form_fields = array(
                'url' => array(
                    'title' => __('backend/configenvironment_url_label', 'woocommerce-finance-gateway'),
                    'type' => 'text',
                    'description' => __('backend/configenvironment_url_description', 'woocommerce-finance-gateway'),
                    'default' => $this->get_default_merchant_api_pub_url($this->api_key),
                ),
                'apiKey' => array(
                    'title' => __('backend/configapi_key_label', 'woocommerce-finance-gateway'),
                    'type' => 'text',
                    'description' => __('backend/configapi_key_description', 'woocommerce-finance-gateway'),
                    'default' => '',
                ),
            );

            if (isset($this->api_key) && $this->api_key) {
                $response = $this->get_all_finances();
                // $settings = $this->get_finance_env();
                $finance = [];
                foreach ($response as $finances) {
                    if ($finances->active) {
                        $finance[$finances->id] = $finances->description;
                    }
                }
                $options = array();

                try {
                    foreach ($finance as $key => $descriptions) {
                        $options[$key] = $descriptions;
                    }
                    $this->form_fields = array_merge(
                        $this->form_fields,
                        array(
                            'secret' => array(
                                'title' => __('backend/configshared_secret_label', 'woocommerce-finance-gateway'),
                                'type' => 'text',
                                'description' => __('backend/configshared_secret_description', 'woocommerce-finance-gateway'),
                                'default' => '',
                            ),
                            'enabled' => array(
                                'title' => __('backend/configplugin_active_label', 'woocommerce-finance-gateway'),
                                'label' => __('backend/pluginenabled_option', 'woocommerce-finance-gateway'),
                                'type' => 'checkbox',
                                'description' => __('backend/configplugin_active_description', 'woocommerce-finance-gateway'),
                                'default' => 'no',
                            ),
                            'title' => array(
                                'title' => __('backend/configcheckout_title_label', 'woocommerce-finance-gateway'),
                                'type' => 'text',
                                'description' => __('backend/configcheckout_title_description', 'woocommerce-finance-gateway'),
                                'default' => __('frontend/checkoutcheckout_title_default', 'woocommerce-finance-gateway'),
                            ),
                            'description' => array(
                                'title' => __('backend/configcheckout_description_label', 'woocommerce-finance-gateway'),
                                'type' => 'text',
                                'description' => __('backend/configcheckout_description_description', 'woocommerce-finance-gateway'),
                                'default' => __('frontend/checkoutcheckout_description_default', 'woocommerce-finance-gateway'),
                            ),
                            'General Settings' => array(
                                'title' => __('backend/configgeneral_settings_header', 'woocommerce-finance-gateway'),
                                'type' => 'title',
                                'class' => 'border',
                            ),
                        )
                    );
                    $this->form_fields['showFinanceOptions'] = array(
                        'title' => __('backend/configlimit_plans_label', 'woocommerce-finance-gateway'),
                        'type' => 'select',
                        'description' => __('backend/configlimit_plans_description', 'woocommerce-finance-gateway'),
                        'default' => 'all',
                        'options' => array(
                            'all'       => __('backend/configshow_all_plans_option', 'woocommerce-finance-gateway'),
                            'selection' => __('backend/configselect_specific_plans_option', 'woocommerce-finance-gateway')
                        ),
                    );
                    $this->form_fields['showFinanceOptionSelection'] = array(
                        'title' => __('backend/configrefine_plans_label', 'woocommerce-finance-gateway'),
                        'type' => 'multiselect',
                        'options' => $options,
                        'description' => __('backend/configrefine_plans_instructions', 'woocommerce-finance-gateway'),
                        'default' => 'all',
                        'class' => 'border_height',
                    );

                    $this->form_fields = array_merge(
                        $this->form_fields,
                        array(
                            'cartThreshold' => array(
                                'title' => __('backend/configcart_threshold_label', 'woocommerce-finance-gateway'),
                                'type' => 'text',
                                'description' => __('backend/configcart_threshold_description', 'woocommerce-finance-gateway'),
                                'default' => '250',
                            ),
                            'maxLoanAmount' => array(
                                'title' => __('backend/configcart_maximum_label', 'woocommerce-finance-gateway'),
                                'type' => 'text',
                                'description' => __('backend/configcart_maximum_description', 'woocommerce-finance-gateway'),
                                'default' => '25000',
                            ),
                            'productSelect' => array(
                                'title' => __('backend/configproduct_selection_label', 'woocommerce-finance-gateway'),
                                'type' => 'select',
                                'default' => 'All',
                                'options' => array(
                                    'all' => __('backend/configfinance_all_products_option', 'woocommerce-finance-gateway'),
                                    'selected' => __('backend/configfinance_specific_products_option', 'woocommerce-finance-gateway'),
                                    'price' => __('backend/configfinance_threshold_products_option', 'woocommerce-finance-gateway'),
                                ),
                            ),
                            'priceSelection' => array(
                                'title' => __('backend/configproduct_price_threshold_label', 'woocommerce-finance-gateway'),
                                'type' => 'text',
                                'description' => __('backend/configproduct_price_threshold_description', 'woocommerce-finance-gateway'),
                                'default' => '350',
                            ),
                            'Widget Settings' => array(
                                'title' => __('backend/configwidget_settings_header', 'woocommerce-finance-gateway'),
                                'type' => 'title',
                                'class' => 'border',
                            ),
                            'showWidget' => array(
                                'title' => __('backend/configshow_widget_label', 'woocommerce-finance-gateway'),
                                'type' => 'select',
                                'default' => 'show',
                                'description' => __('backend/configshow_widget_description', 'woocommerce-finance-gateway'),
                                'options' => array(
                                    'show' => __('globalyes', 'woocommerce-finance-gateway'),
                                    'disabled' => __('globalno', 'woocommerce-finance-gateway'),
                                ),
                            ),
                            'calculatorTheme' => array(
                                'title' => __('backend/configwidget_mode_label', 'woocommerce-finance-gateway'),
                                'type' => 'select',
                                'default' => 'enabled',
                                'description' => __('backend/configwidget_mode_description', 'woocommerce-finance-gateway'),
                                'options' => array(
                                    'enabled' => __('backend/configcalculator_option', 'woocommerce-finance-gateway'),
                                    'disabled' => __('backend/configlightbox_option', 'woocommerce-finance-gateway'),
                                ),
                            ),
                            'widgetThreshold' => array(
                                'title' => __('backend/configwidget_minimum_label', 'woocommerce-finance-gateway'),
                                'type' => 'text',
                                'description' => __('backend/configwidget_minimum_description', 'woocommerce-finance-gateway'),
                                'default' => '250',
                            ),
                            'buttonText' => array(
                                'title' => __('backend/configwidget_button_text_label', 'woocommerce-finance-gateway'),
                                'type' => 'text',
                                'description' => __('backend/configwidget_button_text_description', 'woocommerce-finance-gateway'),
                                'default' => '',
                            ),
                            'footnote' => array(
                                'title' => __('backend/configwidget_footnote_label', 'woocommerce-finance-gateway'),
                                'type' => 'text',
                                'description' => __('backend/configwidget_footnote_description', 'woocommerce-finance-gateway'),
                                'default' => '',
                            ),
                            'useStoreLanguage' => array(
                                'title' => __('backend/configuse_store_language_label', 'woocommerce-finance-gateway'),
                                'label' => __('backend/pluginenabled_option', 'woocommerce-finance-gateway'),
                                'type' => 'checkbox',
                                'description' => __('backend/configuse_store_language_description', 'woocommerce-finance-gateway'),
                                'default' => 'no'
                            ),
                            'Order Settings' => array(
                                'title' => __('backend/configorder_settings_header', 'woocommerce-finance-gateway'),
                                'type' => 'title',
                                'class' => 'border',
                            ),
                            'autoFulfillment' => array(
                                'title' => __('backend/configautomatic_fulfilment_label', 'woocommerce-finance-gateway'),
                                'label' => __('backend/pluginenabled_option', 'woocommerce-finance-gateway'),
                                'type' => 'checkbox',
                                'description' => __('backend/configautomatic_fulfilment_description', 'woocommerce-finance-gateway'),
                                'default' => "yes",
                            ),
                            'autoRefund' => array(
                                'title' => __('backend/configautomatic_refund_label', 'woocommerce-finance-gateway'),
                                'label' => __('backend/pluginenabled_option', 'woocommerce-finance-gateway'),
                                'type' => 'checkbox',
                                'description' => __('backend/configautomatic_refund_description', 'woocommerce-finance-gateway'),
                                'default' => "yes",
                            ),
                            'autoCancel' => array(
                                'title' => __('backend/configautomatic_cancellation_label', 'woocommerce-finance-gateway'),
                                'label' => __('backend/pluginenabled_option', 'woocommerce-finance-gateway'),
                                'type' => 'checkbox',
                                'description' => __('backend/configautomatic_cancellation_description', 'woocommerce-finance-gateway'),
                                'default' => "yes",
                            ),
                        )
                    );
                } catch (Exception $e) {
                    return [];
                }
            }
        }

        /**
         * take language part of locale
         * @return bool|string
         */
        public function get_language()
        {
            return substr(get_locale(), 0, 2);
        }

        /**
         * Admin Panel Options
         * - Payment options
         * @since 1.0.0
         *
         */
        function admin_options()
        {
            $sdk = Merchant_SDK::getSDK($this->url, $this->api_key);

            $status_code = null;

            if($sdk !== null){
                $response = $sdk->health()->checkHealth();

                if(array_key_exists('status_code', $response) && !empty($response['status_code'])){
                    $status_code = $response['status_code'];
                }
            }

            $bad_host = !$status_code;
            $not_200 = $status_code !== 200;
        ?>
<h3>
    <?php esc_html_e('globalplugin_title', 'woocommerce-finance-gateway'); ?>
</h3>
<p>
    <?php esc_html_e('globalplugin_description', 'woocommerce-finance-gateway'); ?>
</p>
<table class="form-table">
    <?php
                $this->init_settings();
                ?>
    <h3 style="border-bottom:1px solid">
        <?php esc_html_e('backend/configgeneral_settings_header', 'woocommerce-finance-gateway'); ?>
    </h3>
    <?php

                    // We can differentiate between bad host and bad URL/health
                    if ($bad_host) {
                        // First catch the case where could not resolve host: {$this->url}
                        // 
                        // environment_url_error: Incorrect or invalid environment URL
                        // environment_url_error_msg: Environment URL is unreachable: {$this->url}

                ?>
    <div style="border:1px solid red;color:red;padding:20px;margin:10px;">
        <b><?php esc_html_e('backend/errorenvironment_url_error', 'woocommerce-finance-gateway'); ?></b>
        <p><?php esc_html_e('backend/errorenvironment_url_error_msg', 'woocommerce-finance-gateway');
                                esc_html_e(" {$this->url}", 'woocommerce-finance-gateway'); ?>
        </p>
    </div>
    <?php

                    } elseif ($not_200) {
                        // Host is good but environment is not healthy
                        //
                        // environment_url_error: Incorrect or invalid environment URL
                        // environment_unhealthy_error_msg: Something may be wrong with the environment. It returned: {$status_code}

                    ?>
    <div style="border:1px solid red;color:red;padding:20px;margin:10px;">
        <b><?php esc_html_e('backend/errorenvironment_url_error', 'woocommerce-finance-gateway'); ?></b>
        <p><?php esc_html_e('backend/errorenvironment_unhealthy_error_msg', 'woocommerce-finance-gateway'); ?>
            <?php esc_html_e(" {$status_code}", 'woocommerce-finance-gateway'); ?>
        </p>
    </div>
    <?php
                    }

                if (isset($this->api_key) && $this->api_key) {
                    $response = $this->get_all_finances();
                    $options = array();
                    if ([] === $response) {
                    ?>
    <div style="border:1px solid red;color:red;padding:20px;margin:10px;">
        <b><?php esc_html_e('backend/errorinvalid_api_key_error', 'woocommerce-finance-gateway'); ?></b>
        <p><?php esc_html_e('backendcontact_financier_msg', 'woocommerce-finance-gateway'); ?></p>
    </div>
    <?php
                    }
                }

                $this->generate_settings_html();
                ?>
</table>
<!--/.form-table-->

<script type="text/javascript">
jQuery(document).ready(function($) {
    function checkFinanceSettings() {
        $("#woocommerce_finance_priceSelection").parent().parent().parent().hide();
        if ($("#woocommerce_finance_productSelect").val() === 'price') {
            $("#woocommerce_finance_priceSelection").parent().parent().parent().show();
        }
        $("#woocommerce_finance_showFinanceOptionSelection").parent().parent().parent().hide();
        if ($("#woocommerce_finance_showFinanceOptions").val() === 'selection') {
            $("#woocommerce_finance_showFinanceOptionSelection").parent().parent().parent().show();
        }
    }

    $("#woocommerce_finance_productSelect,#woocommerce_finance_showFinanceOptions").on('change', function() {
        checkFinanceSettings();
    });
    checkFinanceSettings();
});
</script>
<?php
        }

        /**
         * Get the users country either from their order, or from their customer data.
         */
        function get_country_code()
        {
            global $woocommerce;
            if (isset($_GET['order_id'])) { // Input var okay.
                $order = new WC_Order(sanitize_text_field(wp_unslash($_GET['order_id']))); // Input var okay.

                return $order->billing_country;
            } elseif (version_compare($this->woo_version, '3.0.0') >= 0 && $woocommerce->customer->get_billing_country()) {
                return $woocommerce->customer->get_billing_country(); // Version 3.0+.
            } elseif ($woocommerce->customer->get_country()) {
                return $woocommerce->customer->get_country(); // Version ~2.0.
            }

            return null;
        }

        /**
         * Payment form on checkout page.
         */
        function payment_fields()
        {

            $finances = $this->get_finances($this->get_finance_options());
            if ($finances) {
                $user_country = $this->get_country_code();
                if (empty($user_country)) :
                    esc_html_e('frontend/checkoutchoose_country_msg', 'woocommerce-finance-gateway');

                    return;
                endif;
                if (!in_array($user_country, $this->avaiable_countries, true)) :
                    esc_html_e('frontend/checkout/errorinvalid_country_error', 'woocommerce-finance-gateway');

                    return;
                endif;
                $amount = WC()->cart->total * 100;
                $environment = $this->get_finance_env();
                $plans = $this->get_checkout_plans();
                $footnote = $this->footnote;
                $language = '';
                if ($this->useStoreLanguage === "yes") {
                    $language = 'data-language="' . $this->get_language() . '" ';
                }
                include_once WP_PLUGIN_DIR . '/' . plugin_basename(dirname(__FILE__)) . '/includes/checkout.php';
            }
        }

        /**
         * Process the payment.
         *
         * @param  int $order_id The order id integer.
         * @return array
         *
         * @since 1.0.0
         *
         */
        function process_payment($order_id)
        {
            global $woocommerce;
            $order = new WC_Order($order_id);
            if (
                !isset($_POST['submit-payment-form-nonce'])
                || !wp_verify_nonce($_POST['submit-payment-form-nonce'], 'submit-payment-form')
            ) {
                return;
            }

            $finances = $this->get_finances($this->get_finance_options());
            foreach ($finances as $_finance => $value) {
                if (isset($_POST['divido_plan']) && $_finance === $_POST['divido_plan']) { // Input var okay.
                    $finance = $_finance;
                    $description = $value['description'];
                    $min_deposit = $value['min_deposit'];
                    $max_deposit = $value['max_deposit'];
                }
            }
            if (isset($_finance)) {
                $products = array();
                $order_total = 0;
                foreach ($woocommerce->cart->get_cart() as $item) {
                    if (version_compare($this->get_woo_version(), '3.0.0') >= 0) {
                        $_product = wc_get_product($item['data']->get_id());
                        $name = $_product->get_title();
                    } else {
                        $_product = $item['data']->post;
                        $name = $_product->post_title;
                    }
                    $quantity = $item['quantity'];
                    $price = $item['line_subtotal'] / $quantity * 100;
                    $order_total += $item['line_subtotal'];
                    $products[] = array(
                        'name' => $name,
                        'quantity' => (int) $quantity,
                        'price' => round($price)
                    );
                }
                $deposit = (isset($_POST['divido_deposit']) && round($_POST['divido_deposit']) > 0) ? sanitize_text_field(wp_unslash($_POST['divido_deposit'])) : $min_deposit; // Input var okay.
                if ($woocommerce->cart->needs_shipping()) {
                    $shipping = $order->get_total_shipping();
                    $shipping = (float) $shipping;

                    $products[] = array(
                        'name' =>  __('global/ordershipping_label', 'woocommerce-finance-gateway'),
                        'quantity' => 1,
                        'price' => round($shipping * 100),
                    );
                    // Add shipping to order total.
                    $order_total += $shipping;
                }
                foreach ($woocommerce->cart->get_taxes() as $tax) {
                    $products[] = array(
                        'name' =>  __('global/ordertaxes_label', 'woocommerce-finance-gateway'),
                        'quantity' => 1,
                        'price' => round($tax * 100),
                    );
                    // Add tax to ordertotal.
                    $order_total += $tax;
                }
                foreach ($woocommerce->cart->get_fees() as $fee) {
                    $products[] = array(
                        'name' =>  __('global/orderfees_label', 'woocommerce-finance-gateway'),
                        'quantity' => 1,
                        'price' => round($fee->amount * 100),
                    );
                    if ($fee->taxable) {
                        $products[] = array(
                            'name' =>  __('global/orderfee_tax_label', 'woocommerce-finance-gateway'),
                            'quantity' => 1,
                            'price' => round($fee->tax * 100),
                        );
                        $order_total += $fee->tax;
                    }
                    // Add Fee to order total.
                    $order_total += $fee->amount;
                }
                // Gets the total discount amount(including coupons) - both Taxed and untaxed.
                if ($woocommerce->cart->get_cart_discount_total()) {
                    $products[] = array(
                        'name' =>  __('global/orderdiscount_label', 'woocommerce-finance-gateway'),
                        'quantity' => 1,
                        'price' => round(-$woocommerce->cart->get_cart_discount_total() * 100),
                    );
                    // Deduct total discount.
                    $order_total -= $woocommerce->cart->get_cart_discount_total();
                }
                $other = $order->get_total() - $order_total;
                if (0 !== $other) {
                    $products[] = array(
                        'name' =>  __('global/orderother_label', 'woocommerce-finance-gateway'),
                        'quantity' => 1,
                        'price' => round($other),
                    );
                }

                if (isset($_SERVER['HTTP_RAW_POST_DATA']) && wp_unslash($_SERVER['HTTP_RAW_POST_DATA'])) { // Input var okay.
                    $data = file_get_contents(wp_unslash($_SERVER['HTTP_RAW_POST_DATA'])); // Input var okay.
                } else {
                    $data = file_get_contents('php://input');
                }

                if (empty(get_post_meta($order_id, "_finance_reference", true))) {

                    // Todo: Should check if SDK is not null.
                    $sdk = Merchant_SDK::getSDK($this->url, $this->api_key);

                    $application = (new \Divido\MerchantSDK\Models\Application())
                        ->withCountryId($order->get_billing_country())
                        ->withFinancePlanId($finance)
                        ->withApplicants(
                            [
                                [
                                    'firstName' => $order->get_billing_first_name(),
                                    'lastName' => $order->get_billing_last_name(),
                                    'phoneNumber' => str_replace(' ', '', $order->get_billing_phone()),
                                    'email' => $order->get_billing_email(),
                                    'addresses' => array([
                                        'postcode' => $order->get_billing_postcode(),
                                        'text' => $order->get_billing_postcode() . ' ' . $order->get_billing_address_1() . ' ' . $order->get_billing_city()
                                    ]),
                                ],
                            ]
                        )
                        ->withOrderItems($products)
                        ->withDepositAmount(round($deposit))
                        ->withFinalisationRequired(false)
                        ->withMerchantReference(strval($order_id))
                        ->withUrls([
                            'merchant_redirect_url' => $order->get_checkout_order_received_url(),
                            'merchant_checkout_url' => wc_get_checkout_url(),
                            'merchant_response_url' => admin_url('admin-ajax.php') . '?action=woocommerce_finance_callback',
                        ])
                        ->withMetadata([
                            'order_number' => $order_id,
                            'ecom_platform'         => 'woocommerce',
                            'ecom_platform_version' => WC_VERSION,
                            'ecom_base_url'         => wc_get_checkout_url(),
                            'plugin_version'        => $this->plugin_version,
                            'merchant_reference'    => strval($order_id)
                        ]);
                    if ('' !== $this->secret) {
                        $secret = $this->create_signature(json_encode($application->getPayload()), $this->secret);
                        $response = $sdk->applications()->createApplication($application, [], ['Content-Type' => 'application/json', 'X-Divido-Hmac-Sha256' => $secret]);
                    } else {
                        $response = $sdk->applications()->createApplication($application, [], ['Content-Type' => 'application/json']);
                    }
                    $application_response_body = $response->getBody()->getContents();
                    $decode = json_decode($application_response_body);

                    $result_id = $decode->data->id;
                    $result_redirect = $decode->data->urls->application_url;
                } else {

                    // Todo: Should check if SDK is not null.
                    $sdk = Merchant_SDK::getSDK($this->url, $this->api_key);
                    $applicationId = get_post_meta($order_id, "_finance_reference", true);

                    $application = (new \Divido\MerchantSDK\Models\Application())
                        ->withId($applicationId)
                        ->withCountryId($order->get_billing_country())
                        ->withFinancePlanId($finance)
                        ->withApplicants(
                            [
                                [
                                    'firstName' => $order->get_billing_first_name(),
                                    'lastName' => $order->get_billing_last_name(),
                                    'phoneNumber' => str_replace(' ', '', $order->get_billing_phone()),
                                    'email' => $order->get_billing_email(),
                                    'addresses' => array([
                                        'postcode' => $order->get_billing_postcode(),
                                        'text' => $order->get_billing_postcode() . ' ' . $order->get_billing_address_1() . ' ' . $order->get_billing_city()
                                    ]),
                                ],
                            ]
                        )
                        ->withOrderItems($products)
                        ->withDepositAmount(round(($deposit)))
                        ->withFinalisationRequired(false)
                        ->withMerchantReference(strval($order_id))
                        ->withUrls([
                            'merchant_redirect_url' => $order->get_checkout_order_received_url(),
                            'merchant_checkout_url' => wc_get_checkout_url(),
                            'merchant_response_url' => admin_url('admin-ajax.php') . '?action=woocommerce_finance_callback',
                        ])
                        ->withMetadata([
                            'order_number' => $order_id,
                            'ecom_platform'         => 'woocommerce',
                            'ecom_platform_version' => WC_VERSION,
                            'ecom_base_url'         => wc_get_checkout_url(),
                            'plugin_version'        => $this->plugin_version,
                            'merchant_reference'    => strval($order_id)
                        ]);
                    if ('' !== $this->secret) {
                        $secret = $this->create_signature(json_encode($application->getPayload()), $this->secret);
                        $response = $sdk->applications()->updateApplication($application, [], ['Content-Type' => 'application/json', 'X-Divido-Hmac-Sha256' => $secret]);
                    } else {
                        $response = $sdk->applications()->updateApplication($application, [], ['Content-Type' => 'application/json']);
                    }
                    $application_response_body = $response->getBody()->getContents();
                    $decode = json_decode($application_response_body);
                    $result_id = $decode->data->id;
                    $result_redirect = $decode->data->urls->application_url;
                }
            }

            try {

                update_post_meta($order_id, '_finance_reference', $result_id);
                update_post_meta($order_id, '_finance_description', $description);
                update_post_meta($order_id, '_finance_amount', number_format($order->get_total(), 2, '.', ''));

                return array(
                    'result' => 'success',
                    'redirect' => $result_redirect,
                );
            } catch (Exception $e) {
                $cancel_note = __('backend/orderpayment_rejection_error', 'woocommerce-finance-gateway') . ' (' . __('global/orderapplication_id_label', 'woocommerce-finance-gateway') . ': ' . $order_id . '). ' . __('globalorder_error_description_prefix', 'woocommerce-finance-gateway') . ': "' . $response->error . '". ';
                $order->add_order_note($cancel_note);
                if (version_compare($this->get_woo_version(), '2.1.0') >= 0) {
                    wc_add_notice(__('backend/orderpayment_rejection_error', 'woocommerce-finance-gateway') . ': ' . $decode->data->error . '');
                } else {
                    $woocommerce->add_error(__('backend/orderpayment_rejection_error', 'woocommerce-finance-gateway') . ': ' . $decode->data->error . '');
                }
            }
        }

        /**
         * Get Finances helper function
         *
         * @since 1.0.0
         *
         * @param  boolean $selection true or false depending on checkout or widget use.
         * @return array Array of finances.
         */
        function get_finances($selection = false)
        {
            if (!isset($this->finance_options)) {
                $this->finance_options = $this->get_all_finances();
            }
            $response = $this->finance_options; // array.
            $finances = array();

            try {
                foreach ($response as $_finance) {
                    if ($_finance->active) {
                        if ((!$selection && !is_array($selection)) || in_array($_finance->id, $selection, true)) {
                            $finances[$_finance->id] = $_finance->description;
                            $finances[$_finance->id] = array(
                                'description' => $_finance->description,
                                'min_deposit' => $_finance->deposit->minimum_percentage,
                                'max_deposit' => $_finance->deposit->maximum_percentage,
                            );
                        }
                    }
                }
            } catch (Exception $e) {
                return [];
            } finally {
                return $finances;
            }
        }

        /**
         * Get Finance Platform Environment function
         * @return mixed
         */
        public function get_finance_env()
        {
            $sdk = Merchant_SDK::getSDK($this->url, $this->api_key);

            //Todo: Perhaps show the user some error message? This will break a bunch of stuff.
            if($sdk === null) {
                return '';
            }

            // ensure that the url is used as a part of the cache key so the right env is returned from the cache
            $transient = 'environment' . md5($this->url);
            $setting = get_transient($transient);

            if (!empty($setting)) {
                return $setting;
            } else {
                $response = $sdk->platformEnvironments()->getPlatformEnvironment();
                $finance_env = $response->getBody()->getContents();
                $decoded = json_decode($finance_env);
                $global = $decoded->data->environment;
                set_transient($transient, $global, 60 * 5);

                return $global;
            }
        }

        /**
         * Helper function to get the default merchant api url for the environment
         *
         * @param string $api_key The api key from which to determine the merchant api url
         *
         * @return string The merchant api url based on the api key
         */
        function get_default_merchant_api_pub_url($api_key)
        {
            try {
                // if there is no api key (i.e. a new install), default the merchant api url to an empty string
                if (empty($api_key)) {
                    return '';
                }

                $merchant_sdk_env_config_object = Environment::CONFIGURATION;

                // only default the merchant api url if the url is defined in the merchant SDK
                if (array_key_exists($this->environment, $merchant_sdk_env_config_object)) {
                    return $merchant_sdk_env_config_object[$this->environment]['base_uri'];
                }

                return '';
            } catch (Exception $e) {
                return '';
            }
        }

        /**
         * Enque Admin Styles Updates.
         *
         * @since 1.0.0
         *
         * @return true
         */
        function wpdocs_enqueue_custom_admin_style($hook_suffix)
        {
            // Check if it's the ?page=yourpagename. If not, just empty return before executing the folowing scripts.
            if ('woocommerce_page_wc-settings' !== $hook_suffix) {
                return;
            }
            wp_register_style('woocommerce-finance-gateway-style', plugins_url('', __FILE__) . '/css/style.css', false, 1.0);
            wp_enqueue_style('woocommerce-finance-gateway-style');
        }

        /**
         * Validate the payment form.
         *
         * @since 1.0.0
         *
         * @return true
         */
        function validate_fields()
        {
            return true;
        }

        /**
         * Validate plugin settings.
         *
         * @since 1.0.0
         *
         * @return true
         */
        function validate_settings()
        {
            return true;
        }

        /**
         * Create HMAC SIGNATURE.
         *
         * @since 1.0.0
         *
         * @param  [string] $payload Payload value.
         * @param  [string] $secret  The secret value saved on Finance portal and WordPress.
         * @return string Returns a base64 encoded string.
         */
        public function create_signature($payload, $secret)
        {
            $hmac = hash_hmac('sha256', $payload, $secret, true);
            $signature = base64_encode($hmac);

            return $signature;
        }

        /**
         * Wrapper function for sending JSON.
         *
         * @since 1.0.0
         *
         * @param  [string] $status  The status to send - defaults ok.
         * @param  [string] $message The message to send in the json.
         * @return void
         */
        function send_json($status = 'ok', $message = '')
        {
            $plugindata = get_plugin_data(__FILE__);
            $response = array(
                'status' => $status,
                'message' => $message,
                'platform' => 'Woocommerce',
                'plugin_version' => $plugindata['Version'],
            );
            wp_send_json($response);
        }

        /**
         * Check WooCommerce version.
         *
         * @return string WooCommerce version.
         */
        function get_woo_version()
        {
            if (!function_exists('get_plugins')) {
                include_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $plugin_folder = get_plugins('/woocommerce');
            $plugin_file = 'woocommerce.php';
            if (isset($plugin_folder[$plugin_file]['Version'])) {
                return $plugin_folder[$plugin_file]['Version'];
            } else {
                return null;
            }
        }

        /**
         * Access stored variables in post meta
         *
         * @since 1.0.0
         *
         * @param  [object] $order Instance of wc_get_order.
         * @return array An array containing the finance reference number and the finance id.
         */
        function get_ref_finance($order)
        {
            $result = array(
                'ref' => false,
                'finance' => false,
            );
            if (version_compare($this->woo_version, '3.0.0') >= 0) {
                $ref = get_post_meta($order->get_id(), '_finance_reference', true);
                $finance = get_post_meta($order->get_id(), '_finance', true);
            } else {
                $ref = get_post_meta($order->id, '_finance_reference', true);
                $finance = get_post_meta($order->id, '_finance', true);
            }
            $result['ref'] = $ref;
            $result['finance'] = $finance;

            return $result;
        }

        /**
         * A wrapper to determine if autofulfillment is on whether to send fulfillments.
         *
         * @param [int] $order_id - The woocommerce order id.
         */
        function send_finance_fulfillment_request($order_id)
        {
            $wc_order_id = (string) $order_id;
            $name = get_post_meta($order_id, '_payment_method', true);
            $order = wc_get_order($order_id);
            $order_total = $order->get_total();
            if ('finance' === $name) {
                if ('no' !== $this->auto_fulfillment) {
                    $ref_and_finance = $this->get_ref_finance($order);
                    $this->logger->debug('Finance', 'Auto Fulfillment selected' . $ref_and_finance['ref']);
                    $this->set_fulfilled($ref_and_finance['ref'], $order_total, $wc_order_id);
                    $order->add_order_note(__('globalfinance_label', 'woocommerce-finance-gateway') . ' - ' . __('backend/orderautomatic_fulfillment_sent_msg', 'woocommerce-finance-gateway'));
                } else {
                    $this->logger->debug('Finance', 'Auto Fulfillment not sent');
                }
            } else {
                return false;
            }
        }

        function send_refund_request($order_id)
        {
            $wc_order_id = (string) $order_id;
            $name = get_post_meta($order_id, '_payment_method', true);
            $order = wc_get_order($order_id);
            $order_total = $order->get_total();
            if ('finance' === $name) {
                if ('no' !== $this->auto_refund) {
                    $ref_and_finance = $this->get_ref_finance($order);
                    $this->logger->debug('Finance', 'Auto refund selected' . $ref_and_finance['ref']);
                    $this->set_refund($ref_and_finance['ref'], $order_total, $wc_order_id);
                    $order->add_order_note(__('globalfinance_label', 'woocommerce-finance-gateway') . ' - ' . __('backend/orderautomatic_refund_sent_msg', 'woocommerce-finance-gateway'));
                } else {
                    $this->logger->debug('Finance', 'Auto Refund not sent');
                }
            } else {
                return false;
            }
        }

        function send_cancellation_request($order_id)
        {
            $wc_order_id = (string) $order_id;
            $name = get_post_meta($order_id, '_payment_method', true);
            $order = wc_get_order($order_id);
            $order_total = $order->get_total();
            if ('finance' === $name) {
                if ('no' !== $this->auto_cancel) {
                    $ref_and_finance = $this->get_ref_finance($order);
                    $this->logger->debug('Finance', 'Auto cancellation selected' . $ref_and_finance['ref']);
                    $this->set_cancelled($ref_and_finance['ref'], $order_total, $wc_order_id);
                    $order->add_order_note(__('globalfinance_label', 'woocommerce-finance-gateway') . ' - ' . __('backend/orderautomatic_cancellation_sent_msg', 'woocommerce-finance-gateway'));
                } else {
                    $this->logger->debug('Finance', 'Auto cancellation not sent');
                }
            } else {
                return false;
            }
        }

        /**
         * Function that will activate an application or set to fulfilled on dividio.
         *
         * @since 1.0.0
         *
         * @param  [string] $application_id   - The Finance Application ID - fea4dcb7-e474-4fba-b1a4-123.....
         * @param  [string] $order_total      - Total amount of the order.
         * @param  [string] $order_id         - The Order ID from WooCommerce.
         * @param  [string] $shipping_method  - If the shipping method is set we can apply it here.
         * @param  [string] $tracking_numbers - If there are any tracking numbers to attach we apply here.
         * @return void
         */
        function set_cancelled($application_id, $order_total, $order_id)
        {
            // First get the application you wish to refund.
            $application = (new \Divido\MerchantSDK\Models\Application())
                ->withId($application_id);
            $items = [
                [
                    'name' => __('globalorder_id_label', 'woocommerce-finance-gateway') . ": $order_id",
                    'quantity' => 1,
                    'price' => round($order_total * 100),
                ],
            ];

            $applicationCancellation = (new \Divido\MerchantSDK\Models\ApplicationCancellation())
                ->withOrderItems($items);

            //Todo: Check if SDK is null
            $sdk = Merchant_SDK::getSDK($this->url, $this->api_key);
            $response = $sdk->applicationCancellations()->createApplicationCancellation($application, $applicationCancellation);
            $refundResponseBody = $response->getBody()->getContents();
        }

        function set_refund($application_id, $order_total, $order_id)
        {
            // First get the application you wish to refund.
            $application = (new \Divido\MerchantSDK\Models\Application())
                ->withId($application_id);
            $items = [
                [
                    'name' => __('globalorder_id_label', 'woocommerce-finance-gateway') . ": $order_id",
                    'quantity' => 1,
                    'price' => round($order_total * 100),
                ],
            ];

            $applicationRefund = (new \Divido\MerchantSDK\Models\ApplicationRefund())
                ->withOrderItems($items);

            //Todo: Check if SDK is null
            $sdk = Merchant_SDK::getSDK($this->url, $this->api_key);
            $response = $sdk->applicationRefunds()->createApplicationRefund($application, $applicationRefund);
            $refundResponseBody = $response->getBody()->getContents();
        }

        function set_fulfilled($application_id, $order_total, $order_id, $shipping_method = null, $tracking_numbers = null)
        {
            // First get the application you wish to create an activation for.
            $application = (new \Divido\MerchantSDK\Models\Application())
                ->withId($application_id);
            $items = [
                [
                    'name' => __('globalorder_id_label', 'woocommerce-finance-gateway') . ": $order_id",
                    'quantity' => 1,
                    'price' => round($order_total * 100),
                ],
            ];
            // Create a new application activation model.
            $application_activation = (new \Divido\MerchantSDK\Models\ApplicationActivation())
                ->withOrderItems($items)
                ->withDeliveryMethod($shipping_method)
                ->withTrackingNumber($tracking_numbers);
            // Create a new activation for the application.
            //Todo: Check if SDK is null
            $sdk = Merchant_SDK::getSDK($this->url, $this->api_key);
            $response = $sdk->applicationActivations()->createApplicationActivation($application, $application_activation);
            $activation_response_body = $response->getBody()->getContents();
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
        function finance_gateway_settings_link($links)
        {

            $_link = '<a href="' . esc_url(admin_url('/admin.php?page=wc-settings&tab=checkout&section=finance')) . '">' . __('backendsettings_label', 'woocommerce-finance-gateway') . '</a>';
            $links[] = $_link;

            return $links;
        }
    }

    // end woocommerce_finance.
    global $woocommerce_finance;
    $woocommerce_finance = new WC_Gateway_Finance();
}
