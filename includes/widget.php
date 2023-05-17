<?php defined( 'ABSPATH' ) or die( 'Denied' ); ?>

<div
    data-calculator-widget
    data-mode="lightbox"
    data-plans="<?= $plans;?>"
    data-amount="<?= $price; ?>"
    <?= $language; ?>
    <?= $button_text; ?>
    <?= $footnote; ?>
 >
</div>

<script type='text/javascript'>
    jQuery(document).on('woocommerce_variation_select_change', function() {
        jQuery('.variations_form').each(function() {
            // When variation is found, grab the display price and update Divido_widget
            jQuery(this).on('found_variation', function(event, variation) {
                var new_price = variation.display_price;
                var widget = jQuery("[data-calculator-widget]");
                widget.attr("data-amount", Math.round(new_price * 100));
                <?= (empty($this->calculator_config_api_url)) ? '__widgetInstance' : '__calculator'; ?>.init();
            });
        });
    });
<?php if(!empty($calcConfApiUrl)){ ?>
    window.__calculatorConfig = {
        apiKey: '<?= $shortApiKey ?>',
        calculatorApiPubUrl: '<?= $calcConfApiUrl ?>'
    };
<?php } ?>
</script>
