CHANGELOG for 6.3.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 6.3 minor versions.

To get the diff for a specific change, go to https://github.com/shopware/platform/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/shopware/platform/compare/v6.3.0-rc4...v6.3.0


Table of contents
----------------
* [Table of contents](#table-of-contents)
* [6.3.0](#630)
  - [Administration](#administration)
  - [Core](#core)
  - [Storefront](#storefront)


6.3.0
----------------

#### Administration

* Added custom `slot` to `sw-radio-field` component
* Added some children routes in route `sw.sales.channel.detail.base` in `sw-sales-channel` module to handle step navigation of Google programs modal
* Added `sw-sales-channel-google-programs-modal` component to handle Google programs setup
    * Added `sw-sales-channel-google-introduction` to handle Google account authentication and connection
    * Added `sw-sales-channel-google-authentication` to show Google account profile and handle disconnect functionality
    * Added `sw-sales-channel-google-merchant` component to show existing merchant accounts list and handle assigning existing merchant account or creating new account
    * Added `sw-sales-channel-store-verification` component to show current store verification requirements to use the Shopping ads and a button to check these criteria validation 
    * Added `sw-sales-channel-google-website-verification` component to show show store website information and claim this website for the store    
    * Added `sw-sales-channel-google-terms-verification` component to show the terms and conditions links for Google Merchant Center, Shopping ads policices and Google Ads Terms and conditions. User need to agree with these terms to go to the next step.
    * Added `sw-sales-channel-google-shipping-setting` component to handle shipping setting selection
    * Added `sw-sales-channel-google-done-verification` component to show that user has successfully linked the Google Shopping Merchant and the Sales Channel
* Added `products` children route in `sw.sales.channel.detail.base` route belonging to `sw-sales-channel` module to handle the content navigation of google shopping sales channel
* Added a `Products` navigator in `sw-sales-channel/page/sw-sales-channel-detail` to handle the redirection to the content belonging to `products` children route
* Added `sw-sales-channel-detail-products` component in `sw-sales-channel/view` to show the content belonging to `products` children route
* Modified `sw-sales-channel/view/sw-sales-channel-detail-base` to show the content belonging to only google shopping sales channel
    * Added `isGoogleShopping` flag to distinguish between the content of google shopping sales channel with the others
    * Added `sw-sales-channel-detail-account-connect` component to handle google connection
    * Added `sw-sales-channel-detail-account-disconnect` component to show the google information and also handle google disconnection
* Added salesChannel state in `sw-sales-channel` module
* Added `google-auth.service` to support Google OAuth 2.0
* Added `google-shopping.api.service` to handle Google Shopping API
     * Added method `connectGoogle`
     * Added method `disconnectGoogle`
     * Added method `disconnectGoogle`
     * Added method `getMerchantList`
     * Added method `assignMerchant`
     * Added method `unassignMerchant`
     * Added method `verifyStore`                                   
     * Added method `saveTermsOfService`
     * Added method `setupShipping`
* Refactored sw-settings-custom-field
    * Replaced store with repositories
* Refactored sw-settings-snippet
    * Replaced store with repositories
* Refactored sw-mail-template
    * Replaced store with repositories    

#### Core
* Deprecated `\Shopware\Core\Checkout\Cart\Tax\TaxRuleCalculator`, use `\Shopware\Core\Checkout\Cart\Tax\TaxCalculator` instead

#### Storefront
* Added plugin injection in hot mode

