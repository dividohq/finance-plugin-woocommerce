<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Divido\MerchantSDK\Environment;

/**
 * Finance Payments Blocks integration
 *
 * @since 1.0.3
 */
final class WC_Gateway_Finance_Blocks_Support extends AbstractPaymentMethodType {

    /**
     * The gateway instance.
     *
     * @var WC_Gateway_Finance
     */
    private $gateway;

    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name = 'divido-finance';

    /**
     * Initializes the payment method type.
     */
    public function initialize() {
        $this->settings = get_option( 'woocommerce_divido-finance_settings', [] );
        $gateways       = WC()->payment_gateways->payment_gateways();
        $this->gateway  = $gateways[ $this->name ];
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active() {
        return $this->gateway->is_available();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
        $script_path       = '/assets/js/frontend/blocks.js';
        $script_asset_path = WC_Finance_Payments::plugin_abspath() . '/assets/js/frontend/blocks.asset.php';
        $script_asset      = file_exists( $script_asset_path )
            ? require( $script_asset_path )
            : array(
                'dependencies' => array(),
                'version'      => '1.2.0'
            );
        $script_url        = WC_Finance_Payments::plugin_url() . $script_path;

        wp_register_script(
            'wc-finance-payments-blocks',
            $script_url,
            $script_asset[ 'dependencies' ],
            $script_asset[ 'version' ],
            true
        );

        if ( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations( 'wc-finance-payments-blocks', 'woocommerce-gateway-finance', WC_Finance_Payments::plugin_abspath() . 'i18n/languages/' );
        }

        return [ 'wc-finance-payments-blocks' ];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data() {

        return [
            'name'        => $this->gateway->id,
            'title'       => $this->get_setting( 'title' ),
            'description' => $this->get_setting( 'description' ),
            'footnote' => $this->get_setting( 'footnote' ),
            'logo' => $this->getLogoUrl(),
            'active' => $this->get_setting('enabled') === "yes",
            'plans' => ($this->get_setting('showFinanceOptions') === "all") ? false : $this->get_setting('showFinanceOptionSelection'),
            'supports' => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] )
        ];
    }

    private function getLogoUrl(): ?string {
		$plans = false;
        if(WC_Gateway_Finance::PLAN_CACHING) {
            $plans = get_transient(WC_Gateway_Finance::TRANSIENT_PLANS);
        }
		if(!$plans){
            $apiKey = $this->get_setting('apiKey');
			if(empty($apiKey)){
				return null;
			}

			$apiUrl = $this->get_setting('url');
			if(empty($apiUrl)){
            	$environment = Environment::getEnvironmentFromAPIKey($apiKey);
            	$apiUrl = (array_key_exists($environment, Environment::CONFIGURATION)) ? Environment::CONFIGURATION[$environment]['base_uri'] : null;
			}
			if(empty($apiUrl)){
				return null;
			}
			$plans = WC_Gateway_Finance::get_all_finances($apiUrl, $apiKey);
        }
        if(is_iterable($plans)){
            foreach($plans as $plan){
                if(!empty($plan->lender->branding->logo_url)){
                    return  $plan->lender->branding->logo_url;
                }
            }
        }
        return null;
    }
}