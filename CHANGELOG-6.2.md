CHANGELOG for 6.2.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 6.2 minor versions.

To get the diff for a specific change, go to https://github.com/shopware/platform/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/shopware/platform/compare/v6.2.0-rc4...v6.2.0

### 6.2.0

**Addition / Changes**

* Administration
	* Added `disabled` attribute of fields to `sw-customer-address-form` component
    * Deprecated `tagStore` in `sw-newsletter-recipient-list`
    * Refactored `sw-newsletter-recipient-list`, it now uses `repositoryFactory` instead of `StateDeprecated` for
    fetching and editing data
        * Removed LocalStore
        * Removed StateDeprecated
        * Removed Computed salesChannelStore
        * Removed Computed tagStore
        * Removed Computed tagAssociationStore
    * Moved `sw-manufacturer`, it now uses `repositoryFactory` instead of `StateDeprecated` for 
    fetching and editing data
        * Deprecated `mediaStore`
        * Added `mediaRepository`
        * Deprecated `customFieldSetStore`
        * Added `customFieldSetRepository`
        * Deprecated import of `StateDeprecated`
        * Rewritten `loadEntityData` so it uses the new data handling
        * Added `customFieldSetCriteria` as an computed property
    * Added `disabled` attribute of fields to `sw-customer-address-form` component
    * Refactored sw-radio-field
        * Deprecated currentValue, use value instead
        * Deprecated watcher for value
    * Added "Cache & Indexes" Module to system settings
    * The component sw-integration-list was refactored to use the "repositoryFactory" instead of "StateDeprecated" to fetch and save data
	    - deprecated "StateDeprecated"
	    - change default data "integrations" from "[]" to "null"
	    - deprecated computed "id"
	    - deprecated computed "integrationStore"
	    - deprecated block "sw_integration_list_grid_inner"
	    - deprecated block "sw_integration_list_grid_inner_slot_columns"
	    - deprecated block "sw_integration_list_grid_pagination"
    * Deprecated the use of `fixed-top` class in `header-minimal.html.twig`
    * The component sw-plugin-box was refactored to use the "repositoryFactory" instead of "StateDeprecated" to fetch and save data
            - removed "StateDeprecated"
            - removed computed "pluginStore"
    * The component sw-settings-payment-detail was refactored to use the "repositoryFactory" instead of "StateDeprecated" to fetch and save data
        - removed "StateDeprecated"
        - removed computed "paymentMethodStore"
        - removed computed "ruleStore"
        - removed computed "mediaStore"
    * `sw-settings-custom-field-set`
	    - Removed method which overrides the mixin method `getList`
	    - Add computed property `listingCriteria`
    * `sw-settings-document-list`
        - Removed method which overrides the mixin method `getList`
        - Add computed property `listingCriteria`
    * Refactor  `sw-settings-snippet-list`
        - Remove `StateDeprecated`
        - Remove computed property `snippetSetStore`
        - Add computed property `snippetSetRepository`
        - Add computed property `snippetSetCriteria`
    * Refactor `sw-settings-snippet-set-list`
        - Remove `StateDeprecated`
        - Remove computed property `snippetSetStore`
        - Add computed property `snippetSetRepository`
        - Add computed property `snippetSetCriteria`
        - The method `onConfirmClone` is now an asynchronous method
    * Refactor mixin `sw-settings-list.mixin`
        - Remove `StateDeprecated`
        - Remove computed property `store`
        - Add computed property `entityRepository`
        - Add computed property `listingCriteria`
    * Refactor the module `sw-settings-number-range-detail`
        * Remove LocalStore
        * Remove StateDeprecated
        * Remove data typeCriteria
        * Remove data numberRangeSalesChannelsStore
        * Remove data numberRangeSalesChannels
        * Remove data numberRangeSalesChannelsAssoc
        * Remove data salesChannelsTypeCriteria
        * Remove computed numberRangeStore
        * Remove computed firstSalesChannel
        * Remove computed salesChannelAssociationStore
        * Remove computed numberRangeStateStore
        * Remove computed salesChannelStore
        * Remove computed numberRangeTypeStore
        * Remove method onChange
        * Remove method showOption
        * Remove method getPossibleSalesChannels
        * Remove method setSalesChannelCriteria
        * Remove method enrichAssocStores
        * Remove method onChangeSalesChannel
        * Remove method configHasSaleschannel
        * Remove method selectHasSaleschannel
        * Remove method undeleteSaleschannel
    * Added a new component `sw-order-line-items-grid-sales-channel` which can be used to display line items list in create order page
     * Fixed disabled click event of `router-link` in `sw-context-menu-item`
        - Add `event` and `target` attribute to `<router-link>` to handle with `disabled` prop
        - Add `target` prop to set target options for `<router-link>`
    * Added block `sw_sales_channel_detail_content_tab_analytics` to `sw-sales-channel-detail`, which contains the new Google Analytics tab

    * Added property `isRecordEditable` and `isRecordselectable` to `sw-data-grid`
    * `lerna` package management is marked as optional, got marked as deprecated and will be removed with 6.4
    * Refactored mapErrorService
        * Deprecated `mapApiErrors`, use `mapPropertyErrors`
        * Added `mapCollectionPropertyErrors` to mapErrorService for Entity Collections
    * Fix that user can delete SEO templates accidentally with an empty string in the template text field
* Core    
    * The `Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter` no longer supports `||` and `&&`.
    * The usage of `entity` in the `shopware.entity.definition` tag is deprecated and will be removed with 6.4. 
    * Added `Shopware\Core\Checkout\Cart\Rule\LineItemIsNewRule` to check for newcomers in cart 
    * Added `SalesChannelAnalyticsEntity` to define the Google Analytics configuration
    * Deprecated `\Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextWithHtmlField`, use `\Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField` with `AllowHtml` flag instead
    * Added `lenght`, `width`, `height` variables to `\Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation`  
    * CartBehavior::isRecalculation is deprecated and will be removed in version 6.3
    * Please use context permissions instead:
        * Permissions can be configured in the SalesChannelContext.
        * `CartBehavior` is created based on the permissions from `SalesChannelContext`, you can check the permissions at this class.
        * Permissions exists:
             `ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES`
             `ProductCartProcessor::SKIP_PRODUCT_RECALCULATION`
             `DeliveryProcessor::SKIP_DELIVERY_RECALCULATION`
             `PromotionCollector::SKIP_PROMOTION`
        * Define permissions for AdminOrders at class `SalesChannelProxyController` within the array constant `ADMIN_ORDER_PERMISSIONS`.
        * Define permissions for the Recalculation at class `OrderConverter` within the array constant `ADMIN_ORDER_PERMISSIONS`.
        * Extended permissions with subscribe event `SalesChannelContextPermissionsChangedEvent`, see detail at class `SalesChannelContextFactory`
        
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemOfManufacturerRule` to check the manufacturer of a product in the cart
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemPurchasePriceRule` to check the purchase price of a product in the cart

    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemCreationDateRule` to check the creation date of a product in cart
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemReleaseDateRule` to check the release date of a product in the cart
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemClearanceSaleRule` to check if a clearance sale product is in cart
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemTopsellerRule` to check if a top seller product is in cart
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemInCategoryRule` to check product categories in cart 
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemTaxationRule to check specific taxations in cart 
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemDimensionWidthRule` to check the width of a product in cart
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemDimensionHeightRule` to check the height of a product in cart

* Storefront	
    * The `theme.json` now supports a new option for the `style` files. The placeholder `@StorefrontBootstrap` gives you the ability to use the Bootstrap SCSS without the Shopware Storefront "skin":
        ```json
        {
             "style": [
                  "@StorefrontBootstrap",
                  "app/storefront/src/scss/base.scss"
             ]
        }
         ```
        * The `@StorefrontBootstrap` placeholder also includes the SCSS variables from your `theme.json`.
        * Please beware that this option is only available for the `style` section.
        * You can only use either `@StorefrontBootstrap` or `@Storefront`. They should not be used at the same time. The `@Storefront` bundle includes the Bootstrap SCSS already.
    * We changed the storefront ESLint rule `comma-dangle` to `never`, so that trailing commas won't be forcefully added anymore

**Removals**

* Administration
    * `common` folder with private packages got removed, the packages are public now and are installed from the NPM registry (see: [https://www.npmjs.com/org/shopware-ag](https://www.npmjs.com/org/shopware-ag))

* Core
    *    

* Storefront
    *	
* Core
    * Added hreflang support
