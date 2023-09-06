<?php defined( 'ABSPATH' ) or die( 'Denied' ); ?>

<div
    data-calculator-widget
    data-mode="<?= $mode; ?>"
    data-plans="<?= $plansStr;?>"
    data-amount="<?= $price; ?>"
    <?= $language; ?>
    <?php if(!empty($button_text)) echo("data-button-text=\"{$button_text}\""); ?>
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
                <?= (empty($calcConfApiUrl)) ? '__widgetInstance' : '__calculator'; ?>.init();
            });
        });
    });
</script>
