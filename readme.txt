=== Plugin Name ===
Contributors:      Divido Financial Services Ltd
Plugin Name:       Finance Gateway for WooCommerce
Plugin URI:        integrations.divido.com/woocommerce
Tags:              woothemes,woocommerce,payment gateway,payment,module,ecommerce,online payments,
Author URI:        integrations.divido.com
Author:            Divido Financial Services Ltd
Requires at least: 3.0.2
Tested up to:      5.4.1
Stable tag:        2.1.15
Version:           2.1.15

License: GPLv2 or later

== Description ==
The Finance plugin for WooCommerce allows you to accept finance payments in your WooCommerce store. To get started download the Finance plugin, configure it in settings with your api-key and you’re good to go.

== Installation ==
<strong>Simply follow these steps to install:</strong><br>
1. Unzip the file.<br>
2. Upload the "woocommerce-finance-gateway" folder to your WordPress Plugins directory.<br>
3. Login to your WordPress Admin, then go to Plugins and activate the "Finance Gateway for WooCommerce" plugin<br>
4. Within the WordPress Admin, go to WooCommerce >> Settings, then click on the Payment Gateways tab, then click on the Finance link.<br>
5. Enter the proper information and you are ready to start using the plugin<br>

== Features ==

Shared Secret: Allows you to verify webhooks calls.
Checkout Title: Displays the name of the payment option in the checkout.
Checkout Description: Description of the payment option in the checkout.
Display Plans: Allows you to display all plans or only selected plans.
Plans: Allows you to select the plans that you want to display.
Cart Threshold: Minimum amount that needs to be reached for Finance to be available in the checkout.
Product Selection: Allows you to pick the products on which finance will be available. There are 3 possible options: All Products, Selected Products or Products above Defined Price
Show Product Widget: Allows you to turn on/off the small widget which appears underneath the price on product pages.
Show Calculator Widget: Allows you to turn on/off the Calculator widget which appears at the bottom of product pages.
Widget Threshold: Allows you to set the minimum amount for the "Product Widget" to show.
Widget Prefix: Allows you to add a prefix to the "Product Widget".
Widget Suffix: Allows you to add a suffix to the "Product Widget".
Enable/Disable Automatic Fulfillment: Allows you to select if an "Activation" call should be made automatically to the lender once the order goes to "Completed"
Enable/Disable Automatic Refunds: Allows you to select if a "Refund" call should be made automatically to the lender once the order goes to "Refunded"
Enable/Disable Automatic Cancellation: Allows you to select if an "Cancellation" call should be made automatically to the lender once the order goes to "Cancelled"



 == Changelog ==
Version 2.1.15
Added Translation for DA
Added Translation for NO

Version 2.1.14
Feature - Upgrade Calculator Widget
Fix - Adjust nonce on process payment
Fix - Strip whitepaces from phone number
Chore - Adjust transient deletion

Version 2.1.13
Fix - reverted the text domain untill translations can be adjusted

Version 2.1.12
Fix - Adjusted the text domain
Update - Added allowed countries

Version 2.1.11
Fix - Plugin installation issue

Version 2.1.10
Fix - Editable payment method logo styling

Version 2.1.9
Feature - Add editable payment method logo to checkout

 Version 2.1.8
BugFix - Update widget price for variable products

 Version 2.1.7
BugFix - Widget was not displaying default button text
BugFix - The lightbox mode was not disabled when selecting calculator mode
Chore - Add france and germany to list of approved countries

  Version 2.1.6
Chore  - Additional translations
BugFix - Widget was not displaying unless item was in basket

  Version 2.1.5
Chore  - Adjust naming of functions for consistency
Bugfix - Product edit pages displaing plans that are not active
Chore  - Update Finish Translations

 Version 2.1.4
Fix bug on widget on product page

 Version 2.1.3
Add postcode as address sanity value so that non-existent addresses are cleared

Version 2.1.2
Bugfix - getCheckoutFinanceOptions running on page other than checkout

Version 2.1.1
Rounded deposit to nearest cent/penny
Hide disabled plans in admin panel
Fix hide finance option when no plans available for a given product

Version 2.1.0
Added Translation for DE
Added Translation for GB
Added Translation for US
Added Translation for FI
Added Translation for FR
Adjusted Deposit handling from percentage to Amount

Version 2.0.5
Added PATCH application support
Removed support for Woocommerce less than version 3.0.0

Version 2.0.4
Fixed footnote and button text
Fixed widget threshold

Version 2.0.3
Added FI as accepted county
Added Max Loan amount

Version 2.0.2
Added Environment Testing
Bugfix for Settings link
Add ES as accepted country

Version 2.0.1
Bugfix for Deposit Percentage

Version 2.0.0
Updated Calculator Widget
Replaces prepend and append strings with button text and footnote controls
Updated shortcode helper

Version 1.0.2
Fix to Shared Secret Functionality
Minor Formatting
Added a shortcode helper finance_code - to allow users to place the calculator on non-product pages
Added a settings link to plugins page - quick jump to finance settings.
Added version from @ to comments

Version 1.0.1
Added the ability to Cancel and Refund applications through WooCommerce
Added Transient (caching values) on WooCommerce to prevent unnecessary API calls

Version 1.0.0 Initial Release





