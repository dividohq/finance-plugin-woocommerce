<?php defined( 'ABSPATH' ) or die( 'Denied' ); ?>

<div
    data-calculator-widget
    data-mode="lightbox"
    data-plans="<?php print $plans;?>"
    data-amount="<?php print $price; ?>"
    <?php print $language; ?>
    <?php print $button_text; ?>
    <?php print $footnote; ?>
 >
</div>

<?php if(!empty($calcConfApiUrl)){ ?>
<script type='text/javascript'>
    window.__calculatorConfig = {
        apiKey: '<?= $shortApiKey ?>',
        calculatorApiPubUrl: '<?= $calcConfApiUrl ?>'
    };
</script>
<?php } ?>