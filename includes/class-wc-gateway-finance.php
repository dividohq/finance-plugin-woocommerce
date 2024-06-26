<?php

use Divido\MerchantSDK\Environment;
use Divido\MerchantSDK\Exceptions\MerchantApiBadResponseException;
use Divido\Woocommerce\FinanceGateway\Proxies\MerchantApiPubProxy;

defined('ABSPATH') or die('Denied');
/**
 *  Finance Gateway for Woocommerce
 *
 * @package Finance Gateway
 * @author Divido <support@divido.com>
 * @copyright 2023 Divido Financial Services
 * @license MIT
 *
 */

/**
 * Load the woocommerce plugin.
 */
add_action('plugins_loaded', 'woocommerce_finance_init');

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
    include_once WC_Finance_Payments::plugin_abspath(). '/vendor/autoload.php';
    
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

        private ?float $cart_threshold;

        private ?float $max_loan_amount;

        private ?float $widget_threshold;

        private string $auto_refund;

        private string $auto_cancel;

        private string $environment;

        const PLAN_CACHING = false;
        const PLAN_CACHING_HOURS = 24*7; // 7 days
        const TRANSIENT_PLANS = 'finances';
        const TRANSIENT_APIKEY = 'api_key';

        const V4_CALCULATOR_URL = "https://cdn.divido.com/widget/v4/divido.calculator.js";

        const INLINE_CALCULATOR_MODE = 'calculator';
        const LIGHTBOX_CALCULATOR_MODE = 'lightbox';

        const DEFAULT_SHORTCODE_AMOUNT = 1000;

        const REASONS = [
            "novuna" => [
                "ALTERNATIVE_PAYMENT_METHOD_USED" => "Alternative Payment Method Used",
                "GOODS_FAULTY" => "Goods Faulty",
                "GOODS_NOT_RECEIVED" => "Goods Not Received",
                "GOODS_RETURNED" => "Goods Returned",
                "LOAN_AMENDED" => "Loan Amended",
                "NOT_GOING_AHEAD" => "Not Going Ahead",
                "NO_CUSTOMER_INFORMATION" => "No Customer Information"
            ]
        ];

        const REFUND_ACTION = 'refund';
        const CANCEL_ACTION = 'cancel';

        const 
            STATUS_ACCEPTED = 'ACCEPTED',
            STATUS_ACTION_LENDER = 'ACTION-LENDER',
            STATUS_CANCELED = 'CANCELED',
            STATUS_COMPLETED = 'COMPLETED',
            STATUS_DECLINED = 'DECLINED',
            STATUS_DEPOSIT_PAID = 'DEPOSIT-PAID',
            STATUS_AWAITING_ACTIVATION = 'AWAITING-ACTIVATION',
            STATUS_FULFILLED = 'FULFILLED',
            STATUS_REFERRED = 'REFERRED',
            STATUS_SIGNED = 'SIGNED',
            STATUS_READY = 'READY';

        function wpdocs_load_textdomain()
        {
            if (!load_plugin_textdomain(
                'woocommerce-finance-gateway',
                false,
                WC_Finance_Payments::plugin_abspath(). 'i18n/languages'
            )) {
                $locale = determine_locale();
                if (!load_textdomain(
                    'woocommerce-finance-gateway',
                    WC_Finance_Payments::plugin_abspath() . "i18n/languages/woocommerce-finance-gateway-{$locale}.mo"
                )) {
                    load_textdomain(
                        'woocommerce-finance-gateway',
                        WC_Finance_Payments::plugin_abspath() . 'i18n/languages/woocommerce-finance-gateway-en_GB.mo'
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
            $this->plugin_version = '2.8.2';
            $this->wpdocs_load_textdomain();

            $this->id = 'divido-finance';
            $this->method_title = __('globalplugin_title', 'woocommerce-finance-gateway');
            $this->method_description = __('globalplugin_description', 'woocommerce-finance-gateway');
            $this->has_fields = true;
            // Load the settings.
            $this->init_settings();
            // Get setting values.
            $this->title = isset($this->settings['title']) ? $this->settings['title'] : __('frontend/checkoutcheckout_title_default', 'woocommerce-finance-gateway');
            $this->description = isset($this->settings['description']) ? $this->settings['description'] : __('frontend/checkoutcheckout_description_default', 'woocommerce-finance-gateway');
            $this->calculator_theme = isset($this->settings['calculatorTheme']) ? $this->settings['calculatorTheme'] : 'enabled';
            $this->show_widget = isset($this->settings['showWidget']) ? $this->settings['showWidget'] : true;
            $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : false;
            $this->api_key = $this->settings['apiKey'] ?? '';
            $this->calculator_config_api_url = $this->settings['calcConfApiUrl'] ?? '';
            $this->footnote = $this->settings['footnote'] ?? '';
            $this->buttonText = $this->settings['buttonText'] ?? '';
            
            $this->max_loan_amount = (empty($this->settings['maxLoanAmount']))
                ? null
                : (float) filter_var(
                    $this->settings['maxLoanAmount'], 
                    FILTER_SANITIZE_NUMBER_FLOAT, 
                    FILTER_FLAG_ALLOW_FRACTION
                );
            
            $this->cart_threshold = (empty($this->settings['cartThreshold']))
                ? null
                : (float) filter_var(
                    $this->settings['cartThreshold'], 
                    FILTER_SANITIZE_NUMBER_FLOAT, 
                    FILTER_FLAG_ALLOW_FRACTION
                );
            $this->auto_fulfillment = isset($this->settings['autoFulfillment']) ? $this->settings['autoFulfillment'] : "yes";
            $this->auto_refund = isset($this->settings['autoRefund']) ? $this->settings['autoRefund'] : "yes";
            $this->auto_cancel = isset($this->settings['autoCancel']) ? $this->settings['autoCancel'] : "yes";
            $this->widget_threshold = (empty($this->settings['widgetThreshold']))
                ? null
                : (float) filter_var(
                    $this->settings['widgetThreshold'],
                    FILTER_SANITIZE_NUMBER_FLOAT, 
                    FILTER_FLAG_ALLOW_FRACTION
                );
            $this->secret = $this->settings['secret'] ?? '';
            $this->product_select = $this->settings['productSelect'] ?? '';
            $this->useStoreLanguage = $this->settings['useStoreLanguage'] ?? '';
            // set the environment from the api key
            try {
                $this->environment = Environment::getEnvironmentFromAPIKey($this->api_key);
            } catch (Exception $e) {
                $this->environment = '';
            }
            // set the tenancy environment based on the user input "url" field or default it from the api key
            $this->url = (!empty($this->settings['url'])) ? $this->settings['url'] : $this->get_default_merchant_api_pub_url($this->api_key);

            // Load logger.
            $this->logger = wc_get_logger();

            if (is_admin()) {
                // Load the form fields.
                $this->init_form_fields();
            }

            /** ensures we only add related hooks once (seems to occur twice otherwise) */
            global $hooksAdded;
            if (!isset($hooksAdded)) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options')); // Version 2.0 Hook.
                // product settings.
                add_action('woocommerce_product_write_panel_tabs', array($this, 'product_write_panel_tab'));
                
                add_action('woocommerce_product_data_panels', array($this, 'product_write_panel'));
                
                add_action('woocommerce_process_product_meta', array($this, 'product_save_data'), 10, 2);
                // product page.

                if ('disabled' !== $this->show_widget) {
                    
                    if ('disabled' !== $this->calculator_theme) {
                        add_action('woocommerce_after_single_product_summary', array($this, 'inline_product_calculator'));
                    } else {
                        add_action('woocommerce_single_product_summary', array($this, 'lightbox_product_calculator'));
                    }
                }
                // order admin page (making sure it only adds once).
                add_action('woocommerce_admin_order_data_after_order_details', array($this, 'display_order_data_in_admin'));
            
                // checkout.
                add_filter('woocommerce_payment_gateways', array($this, 'add_method'));

                add_action('admin_head', array($this, 'setConfig'));

                // ajax callback.
                add_action('wp_ajax_nopriv_woocommerce_finance_callback', array($this, 'callback'));
                add_action('wp_ajax_woocommerce_finance_callback', array($this, 'callback'));
                add_action('wp_ajax_woocommerce_finance_status-check', array($this, 'check_status'));
                add_action('wp_ajax_woocommerce_finance_status-update', array($this, 'update_status'));

                //hooks
                add_action('woocommerce_order_status_completed', array($this, 'send_finance_fulfillment_request'), 10, 1);
                
                // shortcodes
                add_shortcode('finance_widget', array($this, 'anypage_widget'));

                // scripts.
                add_action('wp_enqueue_scripts', array($this, 'enqueue_action'));
                
                add_action('admin_enqueue_scripts', array($this, 'wpdocs_enqueue_custom_admin_style'));
                add_action('wp_footer', array($this, 'add_calc_conf_to_footer'));
                
                add_filter('plugin_action_links_' . WC_Finance_Payments::plugin_base_basename(), array($this, 'finance_gateway_settings_link'));

                add_filter('woocommerce_available_payment_gateways', array($this, 'showOptionAtCheckout'));

                $hooksAdded = true;
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
            if (!$this->is_available()) {
                return false;
            }

            $this->enqueue();

            $attributes = shortcode_atts(array(
                'amount' => null,
                'mode' => self::INLINE_CALCULATOR_MODE,
                'button_text' => '',
                'plans' => '',
                'footnote' => ''
            ), $atts, 'finance_widget');

            $mode = ($attributes['mode'] === self::LIGHTBOX_CALCULATOR_MODE)
                ? self::LIGHTBOX_CALCULATOR_MODE
                : self::INLINE_CALCULATOR_MODE;

            $buttonText = '';
            if(!empty($attributes["button_text"] && is_string($attributes['button_text']))){
                $buttonText = sprintf('data-button-text="%s"', $attributes['button_text']);
                $this->buttonText = $attributes['button_text'];
            }

            $footnote = (!empty($attributes["footnote"] && is_string($attributes['footnote'])))
                ? sprintf('data-footnote="%s"', $attributes["footnote"])
                : '';
            
            if (is_product()){
                global $product;
                if($this->doesProductMeetWidgetThreshold($product) === false){
                    return;
                }
                $plans = $this->get_short_plans_array();
                if (
                    isset($this->settings['productSelect'])
                    && $this->settings['productSelect'] === 'selected'
                ) {
                    $plans = $this->filterPlansByProduct($product, $plans);
                }
                if(count($plans) === 0){
                    return;
                }
                $plansStr = $this->convert_plans_to_comma_seperated_string($plans);
                $amount = $this->toPence($product->get_price());
            } else {
                $amount = (isset($attributes['amount']) && is_numeric($attributes['amount']))
                    ? $this->toPence(floatval($attributes['amount']))
                    : $this->toPence(self::DEFAULT_SHORTCODE_AMOUNT);
                
                $plansStr = (!empty($attributes["plans"] && is_string($attributes["plans"])))
                    ? $attributes["plans"]
                    : $this->convert_plans_to_comma_seperated_string($this->get_short_plans_array());
                
            }

            return sprintf(
                '<div data-calculator-widget data-mode="%s" data-amount="%d" %s %s data-plans="%s" ></div>',
                $mode,
                $amount,
                $buttonText,
                $footnote,
                $plansStr
            );

        }

        /**
         * Get the response from the GET /finance-plans API call, either from the cache or API
         *
         * Calls Finance endpoint to return all finances for merchant
         *
         * @since 1.0.0
         *
         * @return iterable
         */
        public static function get_all_finances(string $url, string $apiKey, WC_Logger $logger=null)
        {
            $finances = (self::PLAN_CACHING) ? get_transient(self::TRANSIENT_PLANS) : false;
            if(is_iterable($finances)){
                return $finances;
            }
            $transientApiKey = get_transient(self::TRANSIENT_APIKEY);

            // only fetch new finances if we don't cache plans
            // OR the api key has changed since we last cached plans
            if (!self::PLAN_CACHING || $transientApiKey !== $apiKey) {
                // Retrieve all finance plans for the merchant.
                try {
                    $proxy = new MerchantApiPubProxy($url, $apiKey);

                    $response = $proxy->getFinancePlans();
                    $plans = $response->data;
                    if(self::PLAN_CACHING){
                        set_transient(self::TRANSIENT_PLANS, $plans, 60 * 60 * self::PLAN_CACHING_HOURS);
                        set_transient(self::TRANSIENT_APIKEY, $apiKey, 60 * 60 * self::PLAN_CACHING_HOURS);
                    }
                    return $plans;
                } catch (Exception $e) {
                    if($logger !== null){
                        $logger->debug(sprintf('Error retrieving finance plans: %s', $e->getMessage()));
                    }
                }
            }
            return [];
        }

        function enqueue_action(){
            if ($this->api_key && (is_product() || is_checkout())) {
                $this->enqueue();
            }
        }

        /**
         * Enqeue Add Finance styles and scripts
         *
         * @since 1.0.0
         *
         * @return void
         */
        function enqueue()
        {
            $lender = $this->get_finance_env();
            $isScriptRegistered = false;
            if ($this->isV4()){
                wp_register_script('woocommerce-finance-gateway-calculator', self::V4_CALCULATOR_URL, false, 1.0, true);
                $isScriptRegistered = true;
            } elseif ($this->environment === 'production') {
                wp_register_script('woocommerce-finance-gateway-calculator', sprintf('//cdn.divido.com/widget/v3/%s.calculator.js', $lender), false, 1.0, true);
                $isScriptRegistered = true;
            } elseif($lender !== null) {
                wp_register_script('woocommerce-finance-gateway-calculator', sprintf('//cdn.divido.com/widget/v3/%s.%s.calculator.js', $lender, $this->environment), false, 1.0, true);
                $isScriptRegistered = true;
            }
            wp_register_style('woocommerce-finance-gateway-style', WC_Finance_Payments::plugin_url() . '/css/style.css', false, 1.0);
        
            wp_enqueue_style('woocommerce-finance-gateway-style');
            if($isScriptRegistered){
                wp_enqueue_script('woocommerce-finance-gateway-calculator');
            }
        }

        /**
         * Add Finance Javascript
         *
         * @since 1.0.0
         *
         * @return void
         */
        function add_calc_conf_to_footer()
        {
            if ($this->api_key) {
                $shortKey = preg_split('/\./', $this->api_key)[0];

?>
<script type='text/javascript'>
<?php if(!empty($this->calculator_config_api_url)){ ?>
    window.__calculatorConfig = {
        <?php if(!empty($this->buttonText)){ ?>
        overrides: {
            theme: {
                modes: {
                    Lightbox: {
                        linkText: '<?= $this->buttonText ?>'
                    }
                }
            }
        },
        <?php } ?>
        apiKey: '<?= $shortKey ?>',
        calculatorApiPubUrl: '<?= $this->calculator_config_api_url ?>'
    };
<?php } else { ?>
    window.__widgetConfig = {apiKey: '<?= $shortKey ?>'};
<?php } ?>

</script>
<script>
// <![CDATA[
function waitForElementToDisplay(selector, time) {
    if (document.querySelector(selector) !== null) {
        <?= ($this->isV4()) ? '__calculator' : '__widgetInstance'; ?>.init()
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
                echo(sprintf('<input type=\'hidden\' id=\'financeId\' value=\'%s\'>', esc_html($ref_and_finance['ref'])));
                echo('<div id=\'financeStatusModal\'><div class=\'contents\'></div></div>');
            }
            if ($ref_and_finance['finance']) {
                echo '<p class="form-field form-field-wide"><strong>' . esc_attr(__('backend/orderfinance_plan_id_label', 'woocommerce-finance-gateway')) . ':</strong><br />' . esc_html($ref_and_finance['finance']) . '</p>';
            }
        }

        public function setConfig(){
            echo('<script language=\'javascript\'>');
                echo(sprintf(
                    'const statusCheckPath = \'%s\'', 
                    sprintf(
                        '%s',
                         str_replace(get_site_url(null, '', 'admin'), '', admin_url('admin-ajax.php')))
                )
            );
            echo('</script>');
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
                    $this->logger->debug(sprintf('%s: Hash error', $this->id));
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
                    if (isset($finance_reference[0]) && $finance_reference[0] === $data_json->application) {
                        $order = new WC_Order($data_json->metadata->order_number);
                        $finance_amount = get_post_meta($data_json->metadata->order_number, '_finance_amount');
                        // Check if the requested amount matched order amount.
                        if ($finance_amount[0] !== $order->get_total()) {
                            // Amount mismatch, hold.
                            $order->update_status('on-hold');
                            $order->add_order_note(__('backend/orderorder_amount_error_msg', 'woocommerce-finance-gateway'));
                            $this->logger->debug(
                                sprintf(
                                    '%s: The requested credit of £%s did not match order sum, putting order on hold. Status: %s Order: %s Finance Reference: %s',
                                    $this->id,
                                    $finance_amount[0],
                                    $data_json->status,
                                    $data_json->metadata->order_number,
                                    $finance_reference[0]
                                )
                            );
                            $this->send_json();
                        } else {
                            // Amount matches, update status.

                            switch($data_json->status){
                                case self::STATUS_DECLINED:
                                    $order->update_status('failed');
                                    $this->send_json();
                                    break;
                                case self::STATUS_SIGNED:
                                    $order->update_status('processing');
                                    $this->send_json();
                                    break;
                                case self::STATUS_READY:
                                    $order->add_order_note('Finance status: ' . $data_json->status);
                                    $order->payment_complete();
                                    $this->send_json();
                                    break;
                                case self::STATUS_REFERRED:
                                    $order->add_order_note('Finance status: ' . $data_json->status);
                                    $order->update_status('on-hold');
                                    $this->send_json();
                                    break;
                                case self::STATUS_ACCEPTED:
                                    $order->add_order_note('Finance status: ' . $data_json->status);
                                    $order->update_status('pending-payment');
                                    $this->send_json();
                                    break;
                            }
                        }
                        // Log status to order.
                        $order->add_order_note(__('backend/orderorder_status_label', 'woocommerce-finance-gateway') . ': ' . $data_json->status);
                        $this->logger->debug(
                            sprintf(
                                '%s - Status Update: %s Order: %s Finance Reference: %s',
                                $this->id,
                                $data_json->status,
                                $data_json->metadata->order_number,
                                $finance_reference[0]
                            )
                        );
                    }
                }
            }
        }

        public function check_status(){
            $newStatus = $_GET['status'];
            
            $return = [
                'message' => null,
                'reasons' => null,
                'notify' => false,
                'bypass' => false,
                'action' => 'proceed',
                'title' => 'Do you wish to proceed?'
            ];
            $response = ['Unactionable event'];
            try{
                $application = $this->get_application($_GET['id']);
            } catch(MerchantApiBadResponseException $e){
                $return['message'] = "It appears you are using a different api key...";
                wp_send_json($return);
                return;
            }

            $paymentMethod = get_post_meta($application->merchant_reference, '_payment_method', true);
            if($paymentMethod !== $this->id){
                $return['bypass'] = true;
                wp_send_json($return);
                return;
            }

            if($newStatus === 'wc-cancelled' && $this->auto_cancel === "yes"){
                $cancelable = number_format($application->amounts->cancelable_amount/100, 2);
                $currency = $application->currency->id;
                $amount = sprintf("%s %s", $cancelable, $currency);
                $return['title'] = __('backend/promptcancel_confirmation_prompt', 'woocommerce-finance-gateway');
                $response = [sprintf(
                    __('backend/warningcancel_amount_warning_msg', 'woocommerce-finance-gateway'),
                    $amount,
                    $amount
                )];
                $return['notify'] = true;
                $return['action'] = self::CANCEL_ACTION;

            }elseif($newStatus === 'wc-refunded' && $this->auto_refund === "yes"){
                $application = $this->get_application($_GET['id']);
                $refundable = number_format($application->amounts->refundable_amount/100, 2);
                $currency = $application->currency->id;
                $amount = sprintf("%s %s", $refundable, $currency);
                $return['title'] = __('backend/promptrefund_confirmation_prompt', 'woocommerce-finance-gateway');
                $response = [sprintf(
                    __('backend/warningrefund_amount_warning_msg', 'woocommerce-finance-gateway'),
                    $amount,
                    $amount
                )];
                $return['notify'] = true;
                $return['action'] = self::REFUND_ACTION;
            }
            if(isset(self::REASONS[$application->lender->app_name])){
                $return['reasons'] = self::REASONS[$application->lender->app_name];
                $response[] = sprintf(
                    "%s requests that you provide a reason from the list below:",
                    $application->lender->app_name
                );
            }
            $return['message'] = $response;
            wp_send_json($return);
        }

        public function update_status(){

            $reason = (isset($_GET['reason'])) ? $_GET['reason'] : null;

            $return = [
                'success' => false,
                'message' => 'Nothing happened',
                'reason' => $reason
            ];

            try{
                $application = $this->get_application($_GET['application_id']);

                $paymentMethod = get_post_meta($application->merchant_reference, '_payment_method', true);
                if($paymentMethod !== $this->id){
                    throw new \Exception("This order doesn't appear to be financed");
                }
                $order = wc_get_order($application->merchant_reference);
                if(!$order){
                    throw new \Exception("There was an issue retrieving the order");
                }

                switch($_GET['wf_action']){
                    case self::CANCEL_ACTION:
                        $return['response'] = $this->set_cancelled(
                            $application->id, 
                            $application->amounts->cancelable_amount, 
                            $application->merchant_reference,
                            $reason
                        );
                        $order->add_order_note(__('globalfinance_label', 'woocommerce-finance-gateway') . ' - ' . __('backend/orderautomatic_cancellation_sent_msg', 'woocommerce-finance-gateway'));
                        $return['message'] = 'Lender successfully notified of cancellation request';
                        $return['success'] = true;
                        break;
                    case self::REFUND_ACTION:
                        $return['response'] = $this->set_refund(
                            $application->id, 
                            $application->amounts->refundable_amount, 
                            $application->merchant_reference,
                            $reason
                        );
                        $order->add_order_note(__('globalfinance_label', 'woocommerce-finance-gateway') . ' - ' . __('backend/orderautomatic_refund_sent_msg', 'woocommerce-finance-gateway'));
                        $return['message'] = 'Lender successfully notified of refund request';
                        $return['success'] = true;
                        break;
                    default:
                        $return['message'] = "Could not find action";
                        break;
                }
            }
            catch(MerchantApiBadResponseException $e){
                $return['message'] = sprintf("Application %s not possible: %s", $_GET['wf_action'], $e->getMessage());
                $return['context'] = $e->getContext();
            } catch (\Exception $e) {
                $return['message'] = $e->getMessage();
            }

            wp_send_json($return);
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
         * Returns a float representing the price of this product in pence/pennies
         *
         * @since  1.0.0
         * @param  WC_Product $product Product instance.
         * @return float
         */
        private function get_product_price_inc_tax($product):float
        {
            
            $priceIncludingTax = wc_get_price_including_tax($product);
            
            // $priceIncludingTax could be a float or string
            // Before we do math on this we need to convert the value to a float
            if (!is_float($priceIncludingTax)) {
                $priceIncludingTax = (float) filter_var(
                    $priceIncludingTax,
                    FILTER_SANITIZE_NUMBER_FLOAT, 
                    FILTER_FLAG_ALLOW_FRACTION
                );
            }

            return $priceIncludingTax;
        }

        private function toPence($amount):int{
            $amount = filter_var($amount, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            return (int) ($amount*100);
        }

        /**
         * Check if this gateway is enabled and an api key is set
         *
         * @since 1.0.0
         *
         * @return bool
         */
        public function is_available():bool
        {

            if ('yes' !== $this->enabled || '' === $this->api_key) {
                return false;
            }

            return true;
        }

        /**
         * Get Product specific finance options. Returns an empty array if nothing has been set
         *
         * @since 1.0.0
         *
         * @param  object $product Product Instance.
         * @return array
         */
        public function get_product_finance_plans($product) :array
        {

            if ($product->get_type() === 'variation') {
                $data = maybe_unserialize(get_post_meta($product->get_parent_id(), 'woo_finance_product_tab', true));
            } else {
                $data = maybe_unserialize(get_post_meta($product->get_id(), 'woo_finance_product_tab', true));
            }

            if (isset($data) && is_array($data) && isset($data['active']) && 'selected' === $data['active']) {
                return (is_array($data['finances']) && count($data['finances']) > 0) ? $data['finances'] : array();
            }

            return [];
        }

        /**
         * Get Checkout specific finance plans.
         *
         * @since 1.0.0
         *
         * @param array<ShortPlan> $plans
         * @return string
         */
        public function convert_plans_to_comma_seperated_string(array $plans) :string
        {
            $plansArr = [];

            /** @var Divido\Woocommerce\FinanceGateway\Models\ShortPlan $plan */
            foreach($plans as $plan){
                $plansArr[] = $plan->getId();
            }

            return implode(',', $plansArr);
        }

        /**
         * Display Product calculator widget
         *
         * @return void
         */
        public function product_calculator(string $mode=self::LIGHTBOX_CALCULATOR_MODE)
        {
            global $product;
            if (
                $this->is_available() === false 
                || $this->doesProductMeetWidgetThreshold($product) === false
            ) {
                return;
            }

            $plans = $this->get_short_plans_array();
            if (
                isset($this->settings['productSelect'])
                && $this->settings['productSelect'] === 'selected'
            ) {
                $plans = $this->filterPlansByProduct($product, $plans);
            }
            if(count($plans) === 0){
                return;
            }
            $plansStr = $this->convert_plans_to_comma_seperated_string($plans);
            
            $price = $this->toPence($product->get_price());

            $footnote = (isset($this->footnote) && !empty($this->footnote)) 
                ? sprintf(' data-footnote="%s"', htmlentities($this->footnote)) 
                : "";

            $language = ($this->useStoreLanguage === "yes") 
                ? sprintf("data-language='%s'", $this->get_language())
                : '';
            
            include_once sprintf(
                '%s/includes/widget.php',
                WC_Finance_Payments::plugin_abspath()
            );
            
        }

        /**
         * Show the inline calculator
         *
         * @return void
         */
        public function inline_product_calculator()
        {
            $this->product_calculator(self::INLINE_CALCULATOR_MODE);
        }

        /**
         * Show the lightbox calculator
         *
         * @return void
         */
        public function lightbox_product_calculator()
        {
            $this->product_calculator(self::LIGHTBOX_CALCULATOR_MODE);
        }

        /**
         * Function to add finance product into admin view this add tabs
         *
         * @since 1.0.0
         *
         * @return void
         */
        public function product_write_panel_tab()
        {
            if (!$this->is_available()) {
                return;
            }
            $environment = $this->get_finance_env();
            $tab_icon = 'https://s3-eu-west-1.amazonaws.com/content.divido.com/plugins/powered-by-divido/' . $environment . '/woocommerce/images/finance-icon.png';

            $style = 'content:"";padding:5px 5px 5px 22px; background-image:url(' . $tab_icon . '); background-repeat:no-repeat;background-size: 15px 15px;background-position:8px 8px;';
            $active_style = '';
            
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
            if (!$this->is_available()) {
                return false;
            }
            global $post;
            $tab_data = maybe_unserialize(get_post_meta($post->ID, 'woo_finance_product_tab', true));
            if (empty($tab_data)) {
                $tab_data = array(
                    'active' => 'default',
                    'finances' => array(),
                );
            }
            if (empty($tab_data['finances'])) {
                $tab_data['finances'] = array();
            }
            $finances = $this->get_short_plans_array();

            $warningMsg = sprintf(
                __('backend/warningselected_plans_specific_products_warning_msg', 'woocommerce-finance-gateway'),
                "<b>".__('frontend/productselected_plans_label', 'woocommerce-finance-gateway')."</b>",
                "<b>".__('backend/configproduct_selection_label', 'woocommerce-finance-gateway')."</b>",
                "<b>".__('backend/configfinance_specific_products_option', 'woocommerce-finance-gateway')."</b>"
            )
        ?>
<div id="finance_tab" class="panel woocommerce_options_panel">
    <p class="form-field _hide_title_field ">
        <label
            for="_available"><?php esc_html_e('frontend/productavailable_on_finance_label', 'woocommerce-finance-gateway'); ?></label>

        <input type="radio" class="checkbox" name="_tab_finance_active" id="finance_active_default" value="default"
            <?php print ('default' === $tab_data['active']) ? 'checked="checked"' : ''; ?>>
        <?php esc_html_e('frontend/productdefault_settings_label', 'woocommerce-finance-gateway'); ?>
        <br style="clear:both;" />
        <input type="radio" class="checkbox" name="_tab_finance_active" id="finance_active_selected" value="selected"
            <?php print ('selected' === $tab_data['active']) ? 'checked="checked"' : ''; ?>>
        <?php esc_html_e('frontend/productselected_plans_label', 'woocommerce-finance-gateway'); ?>
        <br style="clear:both;" />
    </p>
    <p class="form-field _hide_title_field" id="selectedFinance" style="display:none;">
        <label
            for="_hide_title"><?php esc_html_e('frontend/productselected_plans_label', 'woocommerce-finance-gateway'); ?></label>

        <?php foreach ($finances as $plan) { ?>
        <input type="checkbox" class="checkbox" name="_tab_finances[]" id="finances_<?php print esc_attr($plan->getId()); ?>"
            value="<?php print esc_attr($plan->getId()); ?>"
            <?php print (in_array($plan->getId(), $tab_data['finances'], true)) ? "checked='checked'" : ''; ?>>
        &nbsp;<?php print esc_attr($plan->getName()); ?>
        <br style="clear:both;" />
        <?php } ?>
        <p><?= $warningMsg ?></p>
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
            $finances = isset($_POST['_tab_finances']) ? wp_unslash($_POST['_tab_finances']) : []; // Input var okay.
            if ((empty($active) || 'default' === $active) && get_post_meta($post_id, 'woo_finance_product_tab', true)) {
                delete_post_meta($post_id, 'woo_finance_product_tab');
            } else {
                // save the data to the database.
                $tab_data = array(
                    'active' => $active,
                    'finances' => $finances
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
                    'title' => $this->_translate('backend/config', 'environment_url_label'),
                    'type' => 'text',
                    'description' => $this->_translate('backend/config', 'environment_url_description'),
                    'default' => $this->get_default_merchant_api_pub_url($this->api_key),
                ),
                'apiKey' => array(
                    'title' => $this->_translate('backend/config', 'api_key_label'),
                    'type' => 'text',
                    'description' => $this->_translate('backend/config', 'api_key_description'),
                    'default' => '',
                )
            );

            if (isset($this->api_key) && $this->api_key) {
                $plans = $this->get_short_plans_array(true, false);

                try {
                    $options = array();
                    /** @var \Divido\Woocommerce\FinanceGateway\Models\ShortPlan $plan */
                    foreach ($plans as $plan) {
                        $options[$plan->getId()] = $plan->getName();
                    }
                    $this->form_fields = array_merge(
                        $this->form_fields,
                        array(
                            'secret' => array(
                                'title' => $this->_translate('backend/config', 'shared_secret_label'),
                                'type' => 'text',
                                'description' => $this->_translate('backend/config', 'shared_secret_description'),
                                'default' => '',
                            ),
                            'Checkout Settings' => array(
                                'title' => $this->_translate('backend/config', 'checkout_settings_header'),
                                'type' => 'title',
                                'class' => 'border',
                            ),
                            'enabled' => array(
                                'title' => $this->_translate('backend/config', 'plugin_active_label'),
                                'label' => $this->_translate('backend/plugin', 'enabled_option'),
                                'type' => 'checkbox',
                                'description' => $this->_translate('backend/config', 'plugin_active_description'),
                                'default' => 'no',
                            ),
                            'title' => array(
                                'title' => $this->_translate('backend/config', 'checkout_title_label'),
                                'type' => 'text',
                                'description' => $this->_translate('backend/config', 'checkout_title_description'),
                                'default' => $this->_translate('frontend/checkout', 'checkout_title_default'),
                            ),
                            'description' => array(
                                'title' => $this->_translate('backend/config', 'checkout_description_label'),
                                'type' => 'text',
                                'description' => $this->_translate('backend/config', 'checkout_description_description'),
                                'default' => $this->_translate('frontend/checkout', 'checkout_description_default'),
                            ),
                            'Conditions Settings' => array(
                                'title' => $this->_translate('backend/config', 'conditions_settings_header'),
                                'type' => 'title',
                                'class' => 'border',
                            ),
                        )
                    );
                    $this->form_fields['showFinanceOptions'] = array(
                        'title' => $this->_translate('backend/config', 'limit_plans_label'),
                        'type' => 'select',
                        'description' => $this->_translate('backend/config', 'limit_plans_description'),
                        'default' => 'all',
                        'options' => array(
                            'all'       => $this->_translate('backend/config', 'show_all_plans_option'),
                            'selection' => $this->_translate('backend/config', 'select_specific_plans_option')
                        ),
                    );
                    $this->form_fields['showFinanceOptionSelection'] = array(
                        'title' => $this->_translate('backend/config', 'refine_plans_label'),
                        'type' => 'multiselect',
                        'options' => $options,
                        'description' => $this->_translate('backend/config', 'refine_plans_instructions'),
                        'default' => 'all',
                        'class' => 'border_height',
                    );

                    $this->form_fields = array_merge(
                        $this->form_fields,
                        array(
                            'cartThreshold' => array(
                                'title' => $this->_translate('backend/config', 'cart_threshold_label'),
                                'type' => 'text',
                                'description' => $this->_translate('backend/config', 'cart_threshold_description')
                            ),
                            'maxLoanAmount' => array(
                                'title' => $this->_translate('backend/config', 'cart_maximum_label'),
                                'type' => 'text',
                                'description' => $this->_translate('backend/config', 'cart_maximum_description')
                            ),
                            'productSelect' => array(
                                'title' => $this->_translate('backend/config', 'product_selection_label'),
                                'type' => 'select',
                                'default' => 'All',
                                'options' => array(
                                    'all' => $this->_translate('backend/config', 'finance_all_products_option'),
                                    'selected' => $this->_translate('backend/config', 'finance_specific_products_option'),
                                    'price' => $this->_translate('backend/config', 'finance_threshold_products_option'),
                                ),
                                'description' => $this->_translate('backend/config', 'product_select_plans_guide_msg')
                            ),
                            'priceSelection' => array(
                                'title' => $this->_translate('backend/config', 'product_price_threshold_label'),
                                'type' => 'text',
                                'description' => $this->_translate('backend/config', 'product_price_threshold_description')
                            ),
                            'Widget Settings' => array(
                                'title' => $this->_translate('backend/config', 'widget_settings_header'),
                                'type' => 'title',
                                'class' => 'border',
                            ),
                            'calcConfApiUrl' => array(
                                'title' => $this->_translate('backend/config', 'calc_conf_api_url_label'),
                                'type' => 'text',
                                'description' => $this->_translate('backend/config', 'calc_conf_api_url_description'),
                                'default' => '',
                            ),
                            'showWidget' => array(
                                'title' => $this->_translate('backend/config', 'show_widget_label'),
                                'type' => 'select',
                                'default' => 'show',
                                'description' => $this->_translate('backend/config', 'show_widget_description'),
                                'options' => array(
                                    'show' => $this->_translate('global', 'yes'),
                                    'disabled' => $this->_translate('global', 'no'),
                                ),
                            ),
                            'calculatorTheme' => array(
                                'title' => $this->_translate('backend/config', 'widget_mode_label'),
                                'type' => 'select',
                                'default' => 'enabled',
                                'description' => $this->_translate('backend/config', 'widget_mode_description'),
                                'options' => array(
                                    'enabled' => $this->_translate('backend/config', 'calculator_option'),
                                    'disabled' => $this->_translate('backend/config', 'lightbox_option'),
                                ),
                            ),
                            'widgetThreshold' => array(
                                'title' => $this->_translate('backend/config', 'widget_minimum_label'),
                                'type' => 'text',
                                'description' => $this->_translate('backend/config', 'widget_minimum_description'),
                            ),
                            'buttonText' => array(
                                'title' => $this->_translate('backend/config', 'widget_button_text_label'),
                                'type' => 'text',
                                'description' => $this->_translate('backend/config', 'widget_button_text_description'),
                                'default' => '',
                            ),
                            'footnote' => array(
                                'title' => $this->_translate('backend/config', 'widget_footnote_label'),
                                'type' => 'text',
                                'description' => $this->_translate('backend/config', 'widget_footnote_description'),
                                'default' => '',
                            ),
                            'useStoreLanguage' => array(
                                'title' => $this->_translate('backend/config', 'use_store_language_label'),
                                'label' => $this->_translate('backend/plugin', 'enabled_option'),
                                'type' => 'checkbox',
                                'description' => $this->_translate('backend/config', 'use_store_language_description'),
                                'default' => 'no'
                            ),
                            'Notifications Settings' => array(
                                'title' => $this->_translate('backend/config', 'notifications_settings_header'),
                                'type' => 'title',
                                'class' => 'border',
                            ),
                            'autoFulfillment' => array(
                                'title' => $this->_translate('backend/config', 'automatic_fulfilment_label'),
                                'label' => $this->_translate('backend/plugin', 'enabled_option'),
                                'type' => 'checkbox',
                                'description' => $this->_translate('backend/config', 'automatic_fulfilment_description'),
                                'default' => "yes",
                            ),
                            'autoRefund' => array(
                                'title' => $this->_translate('backend/config', 'automatic_refund_label'),
                                'label' => $this->_translate('backend/plugin', 'enabled_option'),
                                'type' => 'checkbox',
                                'description' => $this->_translate('backend/config', 'automatic_refund_description'),
                                'default' => "yes",
                            ),
                            'autoCancel' => array(
                                'title' => $this->_translate('backend/config', 'automatic_cancellation_label'),
                                'label' => $this->_translate('backend/plugin', 'enabled_option'),
                                'type' => 'checkbox',
                                'description' => $this->_translate('backend/config', 'automatic_cancellation_description'),
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
            $proxy = new MerchantApiPubProxy($this->url, $this->api_key);

            $status_code = 200;

            try{
                $response = $proxy->getHealth();
            }catch (\Exception $e){
                $status_code = $e->getCode();
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
                    $response = $this->get_all_finances($this->url, $this->api_key);
                    if (empty($response)) {
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
            } elseif ($woocommerce->customer->get_billing_country()) {
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

            $user_country = $this->get_country_code();
            if (empty($user_country)) {
                esc_html_e('frontend/checkoutchoose_country_msg', 'woocommerce-finance-gateway');
                return;
            }

            if (!in_array($user_country, $this->avaiable_countries, true)) {
                esc_html_e('frontend/checkout/errorinvalid_country_error', 'woocommerce-finance-gateway');
                return;
            }

            $amount = $this->toPence(WC()->cart->total);

            $plans = $this->get_short_plans_array();
            if (isset($this->settings['productSelect']) && $this->settings['productSelect'] === 'selected'){
                global $woocommerce;
                $cartItems = array_map(function($item){
                    return $item['data'];
                }, $woocommerce->cart->get_cart_contents());
                $plans = $this->filterPlansByProducts($plans, $cartItems);
            }
            $plansStr = $this->convert_plans_to_comma_seperated_string($plans);

            $footnote = $this->footnote;

            $language = ($this->useStoreLanguage === "yes")
                ? sprintf("data-language='%s'",$this->get_language())
                : '';
            
            $shortApiKey = explode('.',$this->api_key)[0];
            $calcConfApiUrl = $this->calculator_config_api_url;
            include_once sprintf(
                '%s/includes/checkout.php',
                WC_Finance_Payments::plugin_abspath()
            );
            
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

            // if we have come via the classic checkout
            if(
                isset($_POST['submit-payment-form-nonce']) 
                && !wp_verify_nonce($_POST['submit-payment-form-nonce'], 'submit-payment-form')
            ){
                throw new \Exception(
                    esc_html_e('frontend/checkout/errordefault_api_error_msg', 'woocommerce-finance-gateway')
                );
            }
            
            // If no divido plan is set, throw exception
            if (!isset($_POST['divido_plan'])) {
                throw new \Exception(
                    esc_html_e('frontend/checkout/errordefault_api_error_msg', 'woocommerce-finance-gateway')
                );
            }
            
            $products = array();
            $order_total = 0;
            foreach ($woocommerce->cart->get_cart() as $item) {
                
                $_product = wc_get_product($item['data']->get_id());
                $name = $_product->get_title();
                
                $quantity = $item['quantity'];
                $price = $this->toPence($item['line_subtotal'] / $quantity);
                $order_total += $item['line_subtotal'];
                $products[] = array(
                    'name' => $name,
                    'quantity' => (int) $quantity,
                    'price' => round($price),
                    'sku' => $item['data']->get_sku() ?? $item['data']->get_id()
                );
            }
            
            if ($woocommerce->cart->needs_shipping() && $order->get_total_shipping()>0) {
                $shipping = $order->get_total_shipping();

                $products[] = array(
                    'name' =>  __('global/ordershipping_label', 'woocommerce-finance-gateway'),
                    'quantity' => 1,
                    'price' => $this->toPence($shipping),
                    'sku' => 'SHPNG'
                );
                // Add shipping to order total.
                $order_total += $shipping;
            }
            foreach ($woocommerce->cart->get_taxes() as $tax) {
                $products[] = array(
                    'name' =>  __('global/ordertaxes_label', 'woocommerce-finance-gateway'),
                    'quantity' => 1,
                    'price' => $this->toPence($tax),
                    'sku' => 'TAX'
                );
                // Add tax to ordertotal.
                $order_total += $tax;
            }
            foreach ($woocommerce->cart->get_fees() as $fee) {
                $products[] = array(
                    'name' =>  __('global/orderfees_label', 'woocommerce-finance-gateway'),
                    'quantity' => 1,
                    'price' => $this->toPence($fee->amount),
                    'sku' => 'FEES'
                );
                if ($fee->taxable) {
                    $products[] = array(
                        'name' =>  __('global/orderfee_tax_label', 'woocommerce-finance-gateway'),
                        'quantity' => 1,
                        'price' => $this->toPence($fee->tax),
                        'sku' => 'FEE_TAX'
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
                    'price' => $this->toPence(-$woocommerce->cart->get_cart_discount_total()),
                    'sku' => 'DSCNT'
                );
                // Deduct total discount.
                $order_total -= $woocommerce->cart->get_cart_discount_total();
            }
            $other = $this->toPence($order->get_total() - $order_total);
            if ($other !== 0) {
                $products[] = array(
                    'name' =>  __('global/orderother_label', 'woocommerce-finance-gateway'),
                    'quantity' => 1,
                    'price' => $other,
                    'sku' => 'OTHER'
                );
            }

            $proxy = new MerchantApiPubProxy($this->url, $this->api_key);

            $application = (new \Divido\MerchantSDK\Models\Application())
                ->withCountryId($order->get_billing_country())
                ->withFinancePlanId($_POST['divido_plan'])
                ->withApplicants(
                    [
                        [
                            'firstName' => $order->get_billing_first_name(),
                            'lastName' => $order->get_billing_last_name(),
                            'phoneNumber' => str_replace(' ', '', $order->get_billing_phone()),
                            'email' => $order->get_billing_email(),
                            'addresses' => array(array_filter([
                                'co' =>  $order->get_billing_company(),
                                'postcode' => $order->get_billing_postcode(),
                                'country' => $order->get_billing_country(),
                                'text' => implode(', ', array_filter([
                                    $order->get_billing_address_2(),
                                    $order->get_billing_address_1(),
                                    $order->get_billing_city(),
                                ]))
                            ])),
							'shippingAddress' => array_filter([
                                'co' => (empty($order->get_shipping_company())) ? $order->get_billing_company() : $order->get_shipping_company(),
								'postcode' => (empty($order->get_shipping_postcode())) ? $order->get_billing_postcode() : $order->get_shipping_postcode(),
								'country' => (empty($order->get_shipping_country())) ? $order->get_billing_country() : $order->get_shipping_country(),
								'text' => implode(', ', array_filter([
									(empty($order->get_shipping_address_2())) ? $order->get_billing_address_2() : $order->get_shipping_address_2(),
									(empty($order->get_shipping_address_1())) ? $order->get_billing_address_1() : $order->get_shipping_address_1(),
									(empty($order->get_shipping_city())) ? $order->get_billing_city() : $order->get_shipping_city()
								]))
							])
                        ]
                    ]
                )
                ->withOrderItems($products)
                ->withDepositAmount((int) $_POST['divido_deposit'] ?? 0)
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
                $proxy->addSecretHeader($secret);
            }

            try {
                if (empty(get_post_meta($order_id, "_finance_reference", true))) {
                    $response = $proxy->postApplication($application);
                } else {
                    $applicationId = get_post_meta($order_id, "_finance_reference", true);
                    $application = $application->withId($applicationId);
                    $response = $proxy->updateApplication($application);
                }
                
                $result_id = $response->data->id;
                $result_redirect = $response->data->urls->application_url;

                update_post_meta($order_id, '_finance_reference', $result_id);
                update_post_meta($order_id, '_finance_description', $response->data->finance_plan->description ?? $_POST['divido_plan']);
                update_post_meta($order_id, '_finance_amount', number_format($order->get_total(), 2, '.', ''));

                return array(
                    'result' => 'success',
                    'redirect' => $result_redirect,
                );
            } catch (MerchantApiBadResponseException|MerchantApiBadResponseException $e){
                $this->logger->error(sprintf("%s API Error: %s", $this->method_title, $e->getMessage()));
                throw $e;
            } catch (Exception $e) {
                $this->logger->error(sprintf("%s: %s", $this->method_title, $e->getMessage()));
                $cancel_note = sprintf(
                    "%s (%s: %s) %s: %s",
                    __('backend/orderpayment_rejection_error', 'woocommerce-finance-gateway'),
                    __('global/orderapplication_id_label', 'woocommerce-finance-gateway'),
                    $order_id,
                    __('globalorder_error_description_prefix', 'woocommerce-finance-gateway'),
                    $response->data->error ?? $e->getMessage()
                );
                $order->add_order_note($cancel_note);
                
                $data = [
                    'message' => $e->getMessage()
                ];
                if(isset($response->data->error)){
                    $data['response'] = $response->data->error;
                }
                wc_add_notice(
                    sprintf(
                        "%s: %s",
                        __('backend/orderpayment_rejection_error', 'woocommerce-finance-gateway'),
                        $response->data->error ?? $e->getMessage()
                    ),
                    'error',
                    $data
                );
                
            }
        }

        /**
         * Get Finances helper function
         *
         * @since 1.0.0
         *
         * @param  boolean $onlyActive filter out inactive plans if true
         * @param  boolean $refined filter by refined plans list (if limited in config) 
         * @return array Array of finances.
         */
        function get_short_plans_array($onlyActive=true, $refined=true) :array
        {
            $finances = array();
            try {
                if (!isset($this->finance_options)) {
                    $this->finance_options = $this->get_all_finances($this->url, $this->api_key);
                }

                foreach ($this->finance_options as $plan) {
                    $finances[$plan->id] = new Divido\Woocommerce\FinanceGateway\Models\ShortPlan(
                        $plan->id,
                        $plan->description,
                        (int) $plan->credit_amount->minimum_amount,
                        (int) $plan->credit_amount->maximum_amount,
                        $plan->active
                    );
                }
            } catch (Exception $e) {
                $this->logger->debug(sprintf("%s: Error converting finance plans: %s", $this->method_title, $e->getMessage()));
                throw $e;
            }

            $finances = ($onlyActive) 
                ? $this->filterPlansByActive($finances) 
                : $finances;

            $finances = ($refined 
                && isset($this->settings['showFinanceOptions']) 
                && $this->settings['showFinanceOptions'] === 'selection'
            )
                ? $this->filterPlansByRefineList($finances) 
                : $finances;
            
            return $finances;
        }

        function get_application(string $applicationId)
        {
            $proxy = new MerchantApiPubProxy($this->url, $this->api_key);

            $response = $proxy->getApplication($applicationId);

            $application = $response->data;

            return $application;
        }

        /**
         * Get Finance Platform Environment function
         * @return mixed
         */
        public function get_finance_env()
        {
            $proxy = new MerchantApiPubProxy($this->url, $this->api_key);

            // ensure that the url is used as a part of the cache key so the right env is returned from the cache
            $transient = 'environment' . md5($this->url);
            $setting = get_transient($transient);

            if (!empty($setting)) {
                return $setting;
            } else {
                try{
                    $response = $proxy->getEnvironment();
                    $global = $response->data->environment ?? null;
                    set_transient($transient, $global, 60 * 5);

                    return $global;
                } catch (\Exception $e){
                    $this->enabled = false;
                }
            }
            return null;
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
            
            if (get_current_screen()->id == 'shop_order') {
                wp_register_script('woocommerce-finance-gateway-admin-js',plugins_url('/js/admin.js', __FILE__));
                wp_enqueue_script('woocommerce-finance-gateway-admin-js');

                // Enqueue the assets
                wp_enqueue_script('jquery-ui-dialog');
                wp_enqueue_style('wp-jquery-ui-dialog');
              }

            // Check if it's the ?page=yourpagename. If not, just empty return before executing the folowing scripts.
            if ('woocommerce_page_wc-settings' !== $hook_suffix) {
                return;
            }
            wp_register_style('woocommerce-finance-gateway-style', plugins_url('', __FILE__) . '/css/style.css', false, 1.0);
            wp_enqueue_style('woocommerce-finance-gateway-style');
        }

        /**
         * Magic Woocom function called at checkout after the order is submitted
         * Exists to validate the fields created via the payment_fields function
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

            $ref = get_post_meta($order->get_id(), '_finance_reference', true);
            $finance = get_post_meta($order->get_id(), '_finance', true);

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
                    $this->logger->debug(
                        sprintf('%s: Auto Fulfillment selected %s', $this->method_title, $ref_and_finance['ref'])
                    );
                    $this->set_fulfilled($ref_and_finance['ref'], $order_total, $wc_order_id);
                    $order->add_order_note(__('globalfinance_label', 'woocommerce-finance-gateway') . ' - ' . __('backend/orderautomatic_fulfillment_sent_msg', 'woocommerce-finance-gateway'));
                } else {
                    $this->logger->debug(sprintf('%s: Auto Fulfillment not sent', $this->method_title));
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
        function set_cancelled(string $application_id, int $order_total, string $order_id, string $reason=null)
        {
            $items = [
                [
                    'name' => __('globalorder_id_label', 'woocommerce-finance-gateway') . ": $order_id",
                    'quantity' => 1,
                    'price' => $this->toPence($order_total),
                ],
            ];

            $applicationCancellation = (new \Divido\MerchantSDK\Models\ApplicationCancellation())
                ->withOrderItems($items);

            if($reason !== null){
                $applicationCancellation = $applicationCancellation->withReason($reason);
            }

            //Todo: Check if SDK is null
            $proxy = new MerchantApiPubProxy($this->url, $this->api_key);
            $proxy->postCancellation($application_id, $applicationCancellation);
        }

        function set_refund(string $application_id, int $order_total, string $order_id, ?string $reason = null)
        {
            $items = [
                [
                    'name' => __('globalorder_id_label', 'woocommerce-finance-gateway') . ": $order_id",
                    'quantity' => 1,

                    'price' => $this->toPence($order_total)
                ],
            ];

            $applicationRefund = (new \Divido\MerchantSDK\Models\ApplicationRefund())
                ->withOrderItems($items);

            if($reason !== null){
                $applicationRefund = $applicationRefund->withReason($reason);
            }

            //Todo: Check if SDK is null
            $proxy = new MerchantApiPubProxy($this->url, $this->api_key);
            $proxy->postRefund($application_id, $applicationRefund);
        }

        function set_fulfilled($application_id, $order_total, $order_id, $shipping_method = null, $tracking_numbers = null)
        {
            $items = [
                [
                    'name' => __('globalorder_id_label', 'woocommerce-finance-gateway') . ": $order_id",
                    'quantity' => 1,
                    'price' => $this->toPence($order_total),
                ],
            ];
            // Create a new application activation model.
            $application_activation = (new \Divido\MerchantSDK\Models\ApplicationActivation())
                ->withOrderItems($items)
                ->withDeliveryMethod($shipping_method)
                ->withTrackingNumber($tracking_numbers);
            // Create a new activation for the application.
            //Todo: Check if SDK is null
            $proxy = new MerchantApiPubProxy($this->url, $this->api_key);
            $proxy->postActivation($application_id, $application_activation);
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
            $_link = '<a href="' . esc_url(admin_url(sprintf('/admin.php?page=wc-settings&tab=checkout&section=%s', $this->id))) . '">' . __('backendsettings_label', 'woocommerce-finance-gateway') . '</a>';
            $links[] = $_link;

            return $links;
        }

        /**
         * Function to confirm whether the settings supplied are v4 calculator
         * compatible
         *
         * @return boolean
         */
        private function isV4(){
            if(empty($this->calculator_config_api_url)){
                return false;
            } else return true;
        }

        /**
         * Filters out an array of Product objects based on the product price threshold set by the
         * merchant in the config, or false otherwise
         * (generally used at checkout to ascertain if the cart is viable for finance)
         *
         * @param array<WC_Product> $products
         * @return void
         */
        private function doProductsMeetProductPriceThreshold(array $products){
            foreach($products as $item){
                if($this->doesProductMeetPriceThreshold($item) === false){
                    return false;
                }
            }
            return true;
        }

        /**
         * Returns true if the product meets the product price threshold set by the merchant in the config
         * or false otherwise
         * (used at checkout if the merchant only wants all products to be over
         * a certain price) 
         *
         * @param WC_Product $product
         * @return boolean
         */
        private function doesProductMeetPriceThreshold(WC_Product $product):bool{
            $priceThreshold = (float) filter_var(
                $this->settings['priceSelection'] ?? 0, 
                FILTER_SANITIZE_NUMBER_FLOAT, 
                FILTER_FLAG_ALLOW_FRACTION
            );
            if($this->get_product_price_inc_tax($product) < $priceThreshold){
                return false;
            }
            return true;
        }

        /**
         * Returns true if the product price meets the widget threshold set by the merchant in the config
         * or false otherwise
         *
         * @param WC_Product $product
         * @return boolean
         */
        private function doesProductMeetWidgetThreshold(WC_Product $product):bool {
            if($this->get_product_price_inc_tax($product) < $this->widget_threshold){ 
                return false;
            }
            return true;
        }

        /**
         * Filters the plans available based on the plans available for the cart items
         *
         * @param array<ShortPlan> $plans
         * @param array<WC_Product> $products
         * @return array<ShortPlan>
         */
        private function filterPlansByProducts(array $plans, array $products):array{
            foreach($products as $product){
                $plans = $this->filterPlansByProduct($product, $plans);
                if(count($plans) === 0){
                    return $plans;
                }
            }
            return $plans;
        }

        /**
         * Removes plans from an array which are not configured for the product
         *
         * @param WC_Product $product
         * @param array<ShortPlan> $plans
         * @return array<ShortPlan>
         */
        private function filterPlansByProduct(WC_Product $product, array $plans):array{
            $productPlans = $this->get_product_finance_plans($product);
            
            foreach($plans as $key=>$plan){
                if(!in_array($plan->getId(), $productPlans)){
                    unset($plans[$key]);
                }
            }
            return $plans;
        }

        /**
         * Removes any plans from an array of ShortPlans, where the plan is inactive
         *
         * @param array $plans
         * @return array
         */
        private function filterPlansByActive(array $plans):array{
            /** @var \Divido\Woocommerce\FinanceGateway\Models\ShortPlan $plan */
            foreach($plans as $key=>$plan){
                if($plan->isActive() === false){
                    unset($plans[$key]);
                }
            }
            return $plans;
        }

        /**
         * Filters plans by the refined List in the merchant plugin config
         *
         * @param array $plans
         * @return array
         */
        private function filterPlansByRefineList(array $plans): array{
            $refinedPlans = $this->settings['showFinanceOptionSelection'] ?? [];
            
            if(empty($refinedPlans)){
                return $plans;
            }

            foreach($plans as $key=>$plan){
                if(!in_array($plan->getId(), $refinedPlans)){
                    unset($plans[$key]);
                }
            }
            
            return $plans;
        }

        /**
         * Filters out our payment method at checkout if it doesn't fit the config criteria
         *
         * @param array $gateways
         * @return array
         */
        public function showOptionAtCheckout(array $gateways):array{
            if(!isset($gateways[$this->id])){
                return $gateways;
            }

            if(!$this->is_available()){
                unset($gateways[$this->id]);
                return $gateways;
            }

            global $woocommerce;
            if(empty($woocommerce->cart)){
                unset($gateways[$this->id]);
                return $gateways;
            }
                
            $cartTotal = $woocommerce->cart->total;
            // In Cart.
            $settings = $this->settings;
            $threshold = $this->getTrueCartThreshold();
            $upperLimit = $this->getTrueCartMax();

            if ($threshold > $cartTotal || is_float($upperLimit) && $upperLimit < $cartTotal) {
                unset($gateways[$this->id]);
                return $gateways;
            }
            
            $cartItems = array_map(function($item){
                return $item['data'];
            }, $woocommerce->cart->get_cart_contents());

            if (
                isset($settings['productSelect'])
                && $settings['productSelect'] === 'price'
                && $this->doProductsMeetProductPriceThreshold($cartItems) === false
            ){
                unset($gateways[$this->id]);
            } elseif (
                isset($settings['productSelect'])
                && $settings['productSelect'] === 'selected'
                && empty($this->filterPlansByProducts($this->get_short_plans_array(), $cartItems))
            ){
                unset($gateways[$this->id]);
            }

            return $gateways;
        }

        /**
         * Retrieves the actual min credit amount from the plans available
         * Can be overriden by config value if greater than plans min
         *
         * @return float|null
         */
        private function getTrueCartThreshold():?float{
            $min = null;

            foreach($this->get_short_plans_array() as $plan){
                if($min === null || $plan->getCreditMinimum() < $min){
                    $min = $plan->getCreditMinimum();
                }
            }

            if(
                is_float($this->cart_threshold)
                && is_int($min)
                && $this->toPence($this->cart_threshold) > $min
            ){
                return $this->cart_threshold;
            }

            return (is_numeric($min)) ? floatval($min/100) : null;
        }
        
        /**
         * Retrieves the actual min credit amount from the plans available
         * Can be overriden by config values if less than plans min
         *
         * @return float|null
         */
        private function getTrueCartMax():?float{
            $max = null;

            /** @var Divido\Woocommerce\FinanceGateway\Models\ShortPlan $plan */
            foreach($this->get_short_plans_array() as $plan){
                if($max === null || $plan->getCreditMinimum() > $max){
                    $max = $plan->getCreditMaximum();
                }
            }

            if(
                is_float($this->max_loan_amount) 
                && is_int($max)
                && $this->toPence($this->max_loan_amount) < $max
            ){
                return $this->max_loan_amount;
            }

            return (is_numeric($max)) ? floatval($max/100) : null;
        }

        private function _translate($key, $value) {
            return __("{$key}{$value}", "woocommerce-finance-gateway");
        }
    }


    // end woocommerce_finance.
    global $woocommerce_finance;
    $woocommerce_finance = new WC_Gateway_Finance();
}
