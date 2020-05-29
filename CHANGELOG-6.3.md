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
    * Added `sw-sales-channel-google-website-claim` component to input store website information and claim this website for the store    
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
* Refactor `sw-language-info` to context language
* Refactor `sw-language-switch` to context language
* Remove unused `languageStore` from `sw-page`
* Add `initPost` method which starts the `languageAutoFetchingService`
* Add the service `languageAutoFetchingService` for fetching automatically the active language
* Refactor `placeholder.mixin` to context language
* Add language features to Context State
    * Mutation `setApiLanguageId`
    * Mutation `resetLanguageToDefault`
    * Getter `isSystemDefaultLanguage`
* Deprecated LanguageStore
* Refactor `sw-category-detail` to context language
* Refactor `sw-cms-create` to context language
* Refactor `sw-cms-detail` to context language
* Refactor `sw-cms-list` to context language
* Refactor `sw-customer-create` to context language
* Refactor `sw-mail-header-footer-create` to context language
* Refactor `sw-mail-template-create` to context language
* Refactor `sw-mail-template-detail` to context language
* Refactor `sw-mail-template-index` to context language
* Refactor `sw-manufacturer-detail` to context language
* Refactor `sw-newsletter-recipient-list` to context language
* Refactor `sw-order-promotion-tag-field` to context language
* Refactor `sw-order-create-base` to context language
* Refactor `sw-plugin-list` to context language
* Refactor `sw-product-basic-form` to context language
* Refactor `sw-products-variants-generator` to context language
* Refactor `sw-product-detail` to context language
* Refactor `sw-product-list` to context language
* Refactor `sw-promotion-detail` to context language
* Refactor `sw-property-create` to context language
* Refactor `sw-review-detail` to context language
* Refactor `sw-sales-channel-google-introduction` to context language
* Refactor `sw-sales-channel-create` to context language
* Refactor `sw-settings-country-list` to context language
* Refactor `sw-settings-currency-detail` to context language
* Refactor `sw-settings-currency-list` to context language
* Refactor `sw-settings-customer-group-detail` to context language
* Refactor `sw-settings-delivery-time-create` to context language
* Refactor `sw-settings-language-detail` to context language
* Refactor `sw-settings-number-range-create` to context language
* Refactor `sw-settings-payment-create` to context language
* Refactor `sw-settings-payment-list` to context language
* Refactor `sw-settings-salutation-detail` to context language
* Refactor `sw-settings-shipping-detail` to context language
* Refactor `sw-settings-shipping-list` to context language
* Refactor `sw-settings-shopware-updates-wizard` to context language
* Refactor `sw-settings-user-detail` to context language
* Refactored data fetching and saving of `sw-settings-document` module
    * Replaced `StateDeprecated.getStore('document_base_config')` with `this.repositoryFactory.create('document_base_config')`
    * Removed the file `src/module/sw-settings-document/page/sw-settings-document-create/index.js`. The create logic is now handled by `src/module/sw-settings-document/page/sw-settings-document-detail/index.js`
    * `src/module/sw-settings-document/page/sw-settings-document-detail/index.js` changes:
        * Added property `documentConfigId` to `src/module/sw-settings-document/page/sw-settings-document-detail/index.js`
        * Added method `documentBaseConfigCriteria`
        * Added method `createSalesChannelSelectOptions`
        * Added async method `loadAvailableSalesChannel`
        * Changed method name `documentTypeStore` to `documentTypeRepository`
            * It now returns `this.repositoryFactory.create('document_type')` instead of `StateDeprecated.getStore('document_type')`
        * Changed method name `salesChannelStore` to `salesChannelRepository`
            * It now returns `this.repositoryFactory.create('sales_channel')` instead of `StateDeprecated.getStore('sales_channel')`
        * Changed method name `documentBaseConfigSalesChannelAssociationStore` to `documentBaseConfigSalesChannelRepository`
            * It now returns `this.repositoryFactory.create('document_base_config_sales_channel')` instead of `this.documentConfig.getAssociation('salesChannels')`
        * Changed method name `documentBaseConfigStore` to `documentBaseConfigRepository`
            * It now returns `this.repositoryFactory.create('document_base_config')` instead of `StateDeprecated.getStore('document_base_config')`
        * Changed `createdComponent` method to be async now
        * Changed `loadEntityData` method to be async now
        * Changed `onChangeType` method to be async now
        * Removed method `getPossibleSalesChannels`
        * Removed method `setSalesChannelCriteria`
        * Removed method `enrichAssocStores`
        * Removed method `configHasSaleschannel`
        * Removed method `selectHasSaleschannel`
        * Removed method `undeleteSaleschannel`
* Added `block` and `description` property to `sw-radio-field`. Furthermore, each `option` can now also have a `description`

#### Core
* Deprecated `\Shopware\Core\Checkout\Cart\Tax\TaxRuleCalculator`, use `\Shopware\Core\Checkout\Cart\Tax\TaxCalculator` instead
* Added `Criteria $criteria` parameter in store api routes. The parameter will be required in 6.4. At the moment the parameter is commented out in the `*AbstractRoute`, but it is already passed:
    * `Shopware\Core\Checkout\Customer\SalesChannel\AbstractCustomerRoute`
    * `Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute`
    * `Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute`
    * `Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute`
    * `Shopware\Core\Content\Category\SalesChannel\AbstractNavigationRoute`
    * `Shopware\Core\Content\Product\SalesChannel\Listing/AbstractProductListingRoute`
    * `Shopware\Core\Content\Product\SalesChannel\Search/AbstractProductSearchRoute`
    * `Shopware\Core\Content\Seo\SalesChannel\AbstractSeoUrlRoute`
    * `Shopware\Core\System\Currency\SalesChannel\AbstractCurrencyRoute`
    * `Shopware\Core\System\Language\SalesChannel\AbstractLanguageRoute`
    * `Shopware\Core\System\Salutation\SalesChannel\AbstractSalutationRoute`

#### Storefront
* Added plugin injection in hot mode
