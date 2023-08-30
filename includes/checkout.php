<?php defined( 'ABSPATH' ) or die( 'Denied' ); ?>
<p id="finance-payment-description">
   <?php print (isset($this->settings['description'])) ? $this->settings['description']:""; ?>
</p>
<div id="financeWidget"
    data-calculator-widget
    data-mode="calculator"
    data-plans="<?= $plansStr; ?>"
    data-amount="<?= $amount; ?>"
    data-footnote="<?= $footnote; ?>"
    <?= $language; ?>
>
</div>

<script type="text/javascript">
   jQuery(document).ready(function($) {
      waitForElementToDisplay('#financeWidget', 1000);
   });
   <?php if(!empty($calcConfApiUrl)){ ?>
   window.__calculatorConfig = {
      apiKey: '<?= $shortApiKey ?>',
      calculatorApiPubUrl: '<?= $calcConfApiUrl ?>'
   };
<?php } ?>
</script>
<div class="clear"></div>
</fieldset>
<?php wp_nonce_field( 'submit-payment-form', 'submit-payment-form-nonce' ); ?>
