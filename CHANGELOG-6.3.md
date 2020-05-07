CHANGELOG for 6.3.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 6.3 minor versions.

To get the diff for a specific change, go to https://github.com/shopware/platform/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/shopware/platform/compare/v6.3.0-rc4...v6.3.0

### 6.3.0

**Addition / Changes**

* Administration
    * Added custom `slot` to `sw-radio-field` component
    * Added some children routes in route `sw.sales.channel.detail.base` in `sw-sales-channel` module to handle step navigation of Google programs modal 
    * Added `sw-sales-channel-google-programs-modal` component to handle Google programs setup
        * Added `sw-sales-channel-google-introduction` to handle Google account authentication and connection
        * Added `sw-sales-channel-google-authentication` to show Google account profile and handle disconnect functionality
        * Added `sw-sales-channel-google-merchant` component to show existing merchant accounts list and handle assigning existing merchant account or creating new account
        * Added `sw-sales-channel-google-shipping-setting` component to handle shipping setting selection
    * Added salesChannel state in `sw-sales-channel` module
    * Refactored sw-settings-custom-field
        * Replaced store with repositories

    * Added `google-auth.service` to support Google OAuth 2.0
    * Added `google-shopping.api.service` to handle Google Shopping API
        * Added method `connectGoogle`
        * Added method `disconnectGoogle`
        * Added method `disconnectGoogle`
        * Added method `getMerchantList`
        * Added method `assignMerchant`
        * Added method `unassignMerchant`
             
* Core    

* Storefront
    * Added plugin injection in hot mode

    
**Removals**

* Administration

* Core

* Storefront


