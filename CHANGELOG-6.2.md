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
    * Moved `sw-manufacturer`, it now uses `repositoryFactory` instead of `StateDeprecated` for fetching and editing data
        * Deprecated `mediaStore`
        * Deprecated `customFieldSetStore`
        * Deprecated import of `StateDeprecated`
        * Added `mediaRepository`
        * Added `customFieldSetRepository`
        * Added `customFieldSetCriteria` as an computed property
        * Rewritten `loadEntityData` so it uses the new data handling
    * Added `disabled` attribute of fields to `sw-customer-address-form` component
    * Refactored sw-radio-field
        * Deprecated currentValue, use value instead
        * Deprecated watcher for value
    * Added "Cache & Indexes" Module to system settings
    * The component sw-integration-list was refactored to use the `repositoryFactory` instead of `StateDeprecated` to fetch and save data
        * Changed default data `integrations` from `[]` to `null`
        * Deprecated `StateDeprecated`
        * Deprecated computed `id`
        * Deprecated computed `integrationStore`
        * Deprecated block `sw_integration_list_grid_inner`
        * Deprecated block `sw_integration_list_grid_inner_slot_columns`
        * Deprecated block `sw_integration_list_grid_pagination`
    * Deprecated the use of `fixed-top` class in `header-minimal.html.twig`
    * `sw-settings-custom-field-set`
        * Add computed property `listingCriteria`
    * `sw-settings-document-list`
        * Add computed property `listingCriteria`
    * Refactor  `sw-settings-snippet-list`
        * Added computed property `snippetSetRepository`
        * Added computed property `snippetSetCriteria`
    * Refactor `sw-settings-snippet-set-list`
        * Added computed property `snippetSetRepository`
        * Added computed property `snippetSetCriteria`
        * Theed method `onConfirmClone` is now an asynchronous method
    * Refactor mixin `sw-settings-list.mixin`
        * Added computed property `entityRepository`
        * Added computed property `listingCriteria`
    * Added a new component `sw-order-line-items-grid-sales-channel` which can be used to display line items list in create order page
    * Fixed disabled click event of `router-link` in `sw-context-menu-item`
        * Added `event` and `target` attribute to `<router-link>` to handle with `disabled` prop
        * Added `target` prop to set target options for `<router-link>`
    * Added block `sw_sales_channel_detail_content_tab_analytics` to `sw-sales-channel-detail`, which contains the new Google Analytics tab
    * Added property `isRecordEditable` and `isRecordselectable` to `sw-data-grid`
    * `lerna` package management is marked as optional, got marked as deprecated and will be removed with 6.4
    * Refactored mapErrorService
        * Deprecated `mapApiErrors`, use `mapPropertyErrors`
        * Added `mapCollectionPropertyErrors` to mapErrorService for Entity Collections
    * Fix that user can delete SEO templates accidentally with an empty string in the template text field

    * Added `sw-multi-tag-select` component which can now be used to allow users to enter data into a tagged input field
    * Added `sw-multi-tag-ip-select` as an extension which includes IP-validation
    * The `sw-multi-ip-select`-component is now deprecated and will be removed with version 6.4
    
    * Replaced Store based datahandling with repository based datahandling in media specific components and modules, including the following changes
      * sw-tag-field
        * Added injection of `repositoryFactory`
        * Added async computed property associationRepository
      * sw-media-add-thumbnail-form
        * Added prop `disabled`
        * Added method `widthInputCHanged`
        * Added method `heightInputChanged`
        * Added method `inputChanged`
      * sw-media-field is deprecated and replaced by sw-media-field-v2
      * sw-media-folder-content
        * Added injection of `repositoryFactory`
        * Replaced computed property `mediaFolderStore` with `mediaFolderRepository`
        * Method `getSubFolders` is now async
        * Method `fetchParentFolder` is now async
        * Method `updateParentFolder` is now async
      * sw-media-folder-item
        * Added injection of `repositoryFactory`
        * Added computed property `mediaFolderRepository`
        * Added computed property `mediaFolder`
        * Replaced computed property `mediaDefaultFolderStore` with `mediaDefaultFolderRepository`
        * Added `created` hook
        * Added method `createdComponent`
        * Added async method `refreshIconConfig`
        * Method `getIconConfigFromFolder` is now async
        * Method `onChangeName` is now async
      * sw-media-list-selection is deprecated and replaced by sw-media-list-selection-v2 
      * sw-media-media-item
        * Method `onChangeName` is now async
        * Method `emitItemDeleted` is now async
        * Method `onMediaItemMoved` is now async
        * Added method `emitRefreshLibrary`
      * sw-media-modal-delete
        * Added injection of `repositoryFactory`
        * Added computed property `mediaRepository`
        * Added computed property `mediaFolderRepository`
        * Added method `_deleteSelection`
        * Added method `getEntityRepository`
        * Method `deleteSelection` is now async
        * Method `updateSuccessNotification` is now async
      * sw-media-modal-folder-dissolve
        * Method `dissolveSelection` is now async
        * Added async method `_dissolveSelection`
      * sw-media-modal-folder-settings
        * Added injection of `repositoryFactory`
        * Added data property `mediaFolderConfigurationThumbnailSizeRepository`
        * Added data property `deselectedMediaThumbnailSizes`
        * Added data property `disabled`
        * Replaced computed property `mediaFolderStore` with `mediaFolderRepository`
        * Replaced computed property `mediaThumbnailSizeStore` with `mediaThumbnailSizeRepository`
        * Replaced computed property `mediaDefaultFolderStore` with `mediaDefaultFolderRepository`
        * Replaced computed property `mediaFolderConfigurationStore` with `mediaFolderConfigurationRepository`
        * Method `createdComponent` is now async
        * Method `getThumbnailSizes` is now async
        * Method `addThumbnail` is now async
        * Method `deleteThumbnail` is now async
        * Method `onChangeInheritance` is now async
        * Method `onClickSave` is now async
        * Method `ensureUniqueDefaultFolder` is now async
        * Added method `checkIfThumbnailExists`
        * Replaced component `sw-select` with `sw-entity-single-select` 
      * sw-media-modal-move
        * Added injection of `repositoryFactory`
        * Replaced computed property `mediaFolderStore` with `mediaFolderRepository`
        * Replaced computed property `mediaStore` with `mediaRepository`
        * Method `mountedComponent` is now async
        * Method `updateParentFolder` is now async
        * Method `moveSelection` is now async
        * Added async method `_moveSelection`
      * sw-media-modal-replace
        * Added injection of `repositoryFactory`
        * Method `replaceMediaItem` is now async
        * Added event `media-replace-modal-item-replaced`
      * sw-media-preview is deprecated and replaced by sw-media-preview-v2 
      * sw-media-upload is deprecated and replaced by sw-media-upload-v2
      * sw-media-compact-upload is deprecated and replaced by sw-media-compact-upload-v2
      * sw-sidebar-media-item
        * Added injection of `repositoryFactory`
        * Replaced computed property `mediaStore` with `mediaRepository`
        * Replaced computed property `mediaFolderStore` with `mediaFolderRepository`
        * Method `getSubFolders` is now async
        * Method `extendList` is now async
        * Method `getList` is now async
        * Replaced method `getListingParams` with `getListingCriteria`
      * sw-admin
        * Added injection of `loginService`
        * Added computed property `isAuthenticated`
      * sw-duplicated-media is deprecated and replaced sw-duplicated-media-v2
      * sw-media-folder-info
        * Method `onChangeFolderName` is now async
        * Added event `media-folder-renamed`
      * sw-media-quickinfo
        * Added injection of `repositoryFactory`
        * Replaced computed property `mediaStore` with `mediaRepository`
        * Replaced computed property `customFieldSetStore` with `customFieldSetRepository`
        * Added async method `getCustomFieldSets`
        * Added method `emitRefreshMediaLibrary`
        * Method `onSaveCustomFields` is now async
        * Method `onSubmitTitle` is now async
        * Method `onSubmitAltText` is now async
        * Method `onChangeFileName` is now async
        * Added event `media-item-replaced`
      * sw-media-sidebar
        * Added injection of `repositoryFactory`
        * Replaced computed property `mediaFolderStore` with `mediaFolderRepository`
        * Method `fetchCurrentFolder` is now async
        * Added method `onMediaFolderRenamed`
        * Added event `media-sidebar-folder-renamed`
      * sw-media-tag
        * Added injection of `repositoryFactory`
        * Added computed property `mediaRepository`
        * Renamed method `onChange` to `handleChange`
        * Replaced component `sw-tag-field` with `sw-entity-tag-select`
      * sw-media-breadcrumbs
        * Added injection of `repositoryFactory`
        * Added data property `parentFolder`
        * Replaced computed property `mediaFolderStore` with `mediaFolderRepository`
        * Method `updateFolder` is now async
      * sw-media-library
        * Added injection of `repositoryFactory`
        * Replaced computed property `mediaStore` with `mediaRepository`
        * Replaced computed property `mediaFolderStore` with `mediaFolderRepository`
        * Replaced computed property `mediaFolderConfigurationStore` with `mediaFolderConfigurationRepository`
        * Added method `isLoaderDone`
        * Method `refreshList` is now async
        * Method `loadItems` is now async
        * Method `nextFolders` is now async
        * Method `fetchAssociatedFolders` is now async
        * Method `createFolder` is now async
      * sw-media-modal is deprecated and replaced by sw-media-modal-v2
      * sw-media-index
        * Added injection of `repositoryFactory`
        * Added injection of `mediaService`
        * Added data property `parentFolder`
        * Added data property `currentFolder`
        * Added watcher for `routeFolderId`
        * Replaced computed property `mediaStore` with `mediaRepository`
        * Replaced computed property `mediaFolderStore` with `mediaFolderRepository`
        * Added `created` hook
        * Added method `createdComponent`
        * Added async method `updateFolder`
        * Method `onUploadsAdded` is now async
      * sw-product-variants-delivery-media
        * Added injection of `repositoryFactory`
        * Added injection of `mediaService`
        * Replaced computed property `mediaStore` with `mediaRepository`
        * Method `onUploadsAdded` is now async
        * Method `successfulUpload` is now async
      * `sw-property-option-detail
        * Added injection of `repositoryFactory`
        * Method `successfulUpload` is now async  
      * sw-upload-store-listener  is deprecated and replaced by sw-upload-listener
      * sw-cms/elements/image-gallery/config/index.js
        * Method `createdComponent` is now async
      * sw-cms/elements/image-slider/config/index.js
        * Method `createdComponent` is now async
      * sw-cms/elements/image/config/index.js
        * Method `onImageUpload` is now async
      * repository.data
        * Added method `discard`  
      * media.api.service
        * Added method `hasListeners`
        * Added method `hasDefaultListeners`
        * Added method `addListener`
        * Added method `removeListener`
        * Added method `removeDefaultListener`
        * Added method `addDefaultListener`
        * Added method `getListenerForTag`
        * Added method `_createUploadEvent`
        * Added method `addUpload`
        * Added method `addUploads`
        * Added method `removeByTag`
        * Added method `runUploads`
        * Added method `_startUpload`
      * Added possibility to add tabs to Theme Manager
          * Deprecated method `getFields`, use `getStructuredFields` instead
          * Deprecated data `themeFields`, use `structuredThemeFields` instead
          * Added method `getStructuredFields` to themeApiService
    * Added new component `sw-order-create-promotion-modal` which can be used to display the automatic promotions will be disabled before click to the button disable
    * Added an error notification for user when he deletes a customer group that has a SalesChannel and/or a customer assigned to it.
    * Added `bulk-modal-cancel`, `bulk-modal-delete-items`, `delete-modal-cancel` and `delete-modal-delete-item` slots to `sw-entity-listing.html.twig`

    * Removed `v-fixed` directive in `sw-entity-single-select` of `sw-order-product-select`
    * The `fixed` directive is now deprecated and will be removed with version 6.4
    * Added `sw-order-promotion-tag-input` component to handle showing promotion code list, entering and removing promotion code
    * Added `sw-order-create-invalid-promotion-modal` component to show recent invalid promotion codes after clicking on Save Order button
     
    * The `fixed` directive is now deprecated and will be removed with version 6.4 
    * Ordered settings items on settings list index page alphabetically
    * Show error when theme compiling in theme manager throws an error
    * Moved "Customer Group" settings-item from settings-index page to navigation sidebar
    * Moved "Salutation" settings-item from settings-index page to navigation sidebar
    * Add automatic versions to HttpClient. You can override the default version in the config argument
    * Add `Hide products after clearance` option in `Setting -> Shop -> Listing`
    * Add `Listing` tab in the `Storefront presentation` modal to configure the variant preselection
    * Updated Node Dependencies

* Core    
    * The `Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter` no longer supports `||` and `&&`.
    * The usage of `entity` in the `shopware.entity.definition` tag is deprecated and will be removed with 6.4. 
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
             `DeliveryProcessor::SKIP_DELIVERY_PRICE_RECALCULATION`
             `DeliveryProcessor::SKIP_DELIVERY_TAX_RECALCULATION`
             `PromotionCollector::SKIP_PROMOTION`
        * Define permissions for AdminOrders at class `SalesChannelProxyController` within the array constant `ADMIN_ORDER_PERMISSIONS`.
        * Define permissions for the Recalculation at class `OrderConverter` within the array constant `ADMIN_EDIT_ORDER_PERMISSIONS`.
        * Extended permissions with subscribe event `SalesChannelContextPermissionsChangedEvent`, see detail at class `SalesChannelContextFactory`
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemIsNewRule` to check for newcomers in cart 
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemOfManufacturerRule` to check the manufacturer of a product in the cart
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemPurchasePriceRule` to check the purchase price of a product in the cart
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemCreationDateRule` to check the creation date of a product in cart
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemReleaseDateRule` to check the release date of a product in the cart
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemClearanceSaleRule` to check if a clearance sale product is in cart
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemTopsellerRule` to check if a top seller product is in cart
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemInCategoryRule` to check product categories in cart 
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemTaxationRule` to check specific taxation in cart 
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemDimensionWidthRule` to check the width of a product in cart
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemDimensionHeightRule` to check the height of a product in cart
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemDimensionLengthRule` to check the length of a product in cart
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemDimensionWeigthRule` to check the weight of a product in cart
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemListPriceRule` to check if a product with a specific list price is in cart
        * Please use context permissions instead:
            * Permissions can be configured in the SalesChannelContext.
            * `CartBehavior` is created based on the permissions from `SalesChannelContext`, you can check the permissions at this class.
            * Permissions exists:
                 `ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES`
                 `ProductCartProcessor::SKIP_PRODUCT_RECALCULATION`
                 `DeliveryProcessor::SKIP_DELIVERY_PRICE_RECALCULATION`
                 `DeliveryProcessor::SKIP_DELIVERY_TAX_RECALCULATION`
                 `PromotionCollector::SKIP_PROMOTION`
            * Define permissions for AdminOrders at class `SalesChannelProxyController` within the array constant `ADMIN_ORDER_PERMISSIONS`.
            * Define permissions for the Recalculation at class `OrderConverter` within the array constant `ADMIN_ORDER_PERMISSIONS`.
            * Extended permissions with subscribe event `SalesChannelContextPermissionsChangedEvent`, see detail at class `SalesChannelContextFactory`
    * Added hreflang support
    * Added new supported types for the plugin configuration
        * `colorpicker`
        * `url`
        * `checkbox`
        * `date`
        * `time`
    * Added support for several components in the plugin configuration
        * `sw-entity-multi-id-select`
        * `sw-text-editor`
        * `sw-media-field`

    * Added `trackingUrl` property to the `Shopware\Core\Checkout\Shipping\ShippingMethodEntity.php`
    * Added `\Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder` and `\Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\TemplateNamespaceHierarchyBuilderInterface`, that allows to modify twig namespace inheritance
    * Deprecated `\Shopware\Core\Framework\Adapter\Twig\TemplateFinderInterface::registerBundles` use `TemplateNamespaceHierarchyBuilderInterface` to modify twig namespace hierarchy.
    * Deprecated `\Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextWithHtmlField`, use `\Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField` with `AllowHtml` flag instead
    * The `Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter` no longer supports `||` and `&&`.
    * The usage of `entity` in the `shopware.entity.definition` tag is deprecated and will be removed with 6.4. 
    * Added `novelty` rule builder condition-type
    * Added `SalesChannelAnalyticsEntity` to define the Google Analytics configuration
    * Added OrderTransactionStates `failed` and `in_progress`
    * Deprecated `OrderTransactionStateHandler::pay` use `OrderTransactionStateHandler::doPay` instead
    * Deprecated Action Constant `StateMachineTransitionActions::PAY` use `StateMachineTransitionActions::DO_PAY` instead
    * Deprecated route `_action/theme/{themeId}/fields`, use `_action/theme/{themeId}/structured-fields` instead
    * Added new route `_action/theme/{themeId}/structured-fields`
    * Added new `Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts` to provide the possibility to assign individual products to cross selling
    * Added new `\Shopware\Core\Framework\Plugin\BundleConfigGenerator` to generate webpack bundle config and moved the according logic from `\Shopware\Core\Framework\Plugin\BundleConfigDumper` to the new class
    * Deprecated `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::$source`, use `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::$includes` instead
    * Added a `dynamic_mapping` for elasticsearch fields which converts all none mapped string fields to keyword fields instead of text fields. This allows developers to filter to customFields or none mapped associations with elasticsearch.
    * We changed the PaymentHandlerRegistry: This change uses the handler identifier as formatted handler identifier in case it is not splittable by \\. Furthermore the PaymentHandlerRegistry retrieves the payment handlers via the tagged_locator selector which include the id of the payment handler. This change allows paymentHandler to use different ids while using the same Class
    * Deprecated `\Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry:__construct()` TypeHint for both parameters will be changed to ServiceProviderInterface 
    * Deprecated `\Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry:addHandler()` will be removed in 6.3.0
    * Deprecated `\Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface`, extend from abstract class `\Shopware\Core\Framework\DataAbstractionLayer\EntityExtension` instead.
    * Added `defineProtections` method on `\Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition`, which allows to define entity based protections
    * Deprecated `\Shopware\Core\Framework\Routing\RouteScopeInterface` use abstract class `\Shopware\Core\Framework\Routing\AbstractRouteScope` instead
    * Changed `\Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder` to not set default limit for the api listing
    * Added new `\Shopware\Core\Content\ContactForm\SalesChannel\ContactFormRoute` route to make the contact form available using the Store-API
    * Added new `\Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute` to provide payment methods about the new api route `/store-api/v1/payment-method`
    * Added new `\Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute` to provide shipping methods about the new api route `/store-api/v1/shipping-method`
    * Added new `\Shopware\Core\System\Currency\SalesChannel\CurrencyRoute` to provide currencies about the new api route `/store-api/v1/currency`
    * Added new `\Shopware\Core\System\Language\SalesChannel\LanguageRoute` to provide languages about the new api route `/store-api/v1/language`
    * Added new `\Shopware\Core\Content\Category\SalesChannel\CategoryRoute` to provide category page with resolved cms about the new api route `/store-api/v1/category/{categoryId}`
    * Added new `\Shopware\Core\Content\Cms\SalesChannel\CmsRoute` to provide resolved cms page about the new api route `/store-api/v1/cms/{uuid}`
    * Added new `\Shopware\Core\Content\Category\SalesChannel\NavigationRoute` to provide navigation tree of a category about the new api route `/store-api/v1/navigation/{categoryId}`
        * Following alias can be used instead the uuid
            * `main-navigation`
            * `service-navigation`
            * `footer-navigation`
    * Added new `\Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute` to provide listing results about the new api route `/store-api/v1/product-listing/{categoryId}`
    * Added new `\Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRoute` to provide search page results about the new api route `/store-api/v1/search?term=MyKeyword`
    * Added new `\Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRoute` to provide search suggest results about the new api route `/store-api/v1/search-suggest?term=MyKeyword`
    * Added new `\Shopware\Core\Content\Seo\SalesChannel\SeoUrlRoute` to make seo urls available about the new api route `/store-api/v1/seo-url`
    * Added new header `sw-include-seo-urls` for the store-api to enrich the seo urls in the response
    * Added new `\Shopware\Core\System\Salutation\SalesChannel\SalutationRoute` to provide all available salutations with the new api route `/store-api/v1/account/order`
    * Added new `\Shopware\Core\Checkout\Order\SalesChannel\AccountOrderRoute` to provide taken orders of the logged-in customer with the new api route `/store-api/v1/account/order`
    * Added new `\Shopware\Core\Checkout\Customer\SalesChannel\ChangeCustomerProfileRoute` to allow changing profile information of the logged-in customer with the new api route `/store-api/v1/account/change-profile`
    * Added new `\Shopware\Core\Checkout\Customer\SalesChannel\ChangeEmailRoute` to allow changing email of the logged-in customer with the new api route `/store-api/v1/account/change-email`
    * Added new `\Shopware\Core\Checkout\Customer\SalesChannel\ChangePasswordRoute` to allow changing password of the logged-in customer with the new api route `/store-api/v1/account/change-password`
    * Added new `\Shopware\Core\Checkout\Customer\SalesChannel\ChangePaymentMethodRoute` to allow changing payment-method of the logged-in customer with the new api route `/store-api/v1/account/change-payment-method/{uuid}`
    * Added new `\Shopware\Core\Checkout\Customer\SalesChannel\CustomerRoute` to provide information about the current logged-in customer with the new api route `/store-api/v1/account/customer`
    * Added new `\Shopware\Core\Checkout\Customer\SalesChannel\LoginRoute` to login as customer and obtain a context-token with the new api route `/store-api/v1/account/login`
    * Added new `\Shopware\Core\Checkout\Customer\SalesChannel\LogoutRoute` to login as customer and obtain a context-token with the new api route `/store-api/v1/account/logout`
    * Added new `\Shopware\Core\Checkout\Customer\SalesChannel\SendPasswordRecoveryMailRoute` to send a new password recovery mail with the new api route `/store-api/v1/account/send-recovery-mail`
    * Added new `\Shopware\Core\Checkout\Customer\SalesChannel\ResetPasswordRoute` to process the reset password form with the new api route `/store-api/v1/account/reset-password`
    * Added new `\Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute` to subscribe to the newsletter with the new api route `/store-api/v1/newsletter/subscribe`
    * Added new `\Shopware\Core\Content\Newsletter\SalesChannel\NewsletterUnsubscribeRoute` to unsubscribe to the newsletter with the new api route `/store-api/v1/newsletter/unsubscribe`
    * Added new `\Shopware\Core\Content\Newsletter\SalesChannel\NewsletterConfirmRoute` to confirm the newsletter registration with the new api route `/store-api/v1/newsletter/confirm`
    * Added new `\Shopware\Core\Content\Newsletter\SalesChannel\NewsletterConfirmRoute` to confirm the newsletter registration with the new api route `/store-api/v1/newsletter/confirm`
    * Added new `\Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute` to register a new customer with the new api route `/store-api/v1/account/register`
    * Added new `\Shopware\Core\Checkout\Customer\SalesChannel\RegisterConfirmRoute` to confirm a double optin registration with the new api route `/store-api/v1/account/register-confirm`
    
    
    * Deprecated `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistry` use `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistry` instead
    * Added `\Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection::getPayloadsProperty` function which allows to extract a property value of all line item payloads.
    * Added `\Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection::filterByType` function which allows to filter the line item collection by the provided line item type
    * Deprecated `\Shopware\Core\Checkout\Promotion\DataAbstractionLayer\Indexing\PromotionExclusionIndexer`, use \Shopware\Core\Checkout\Promotion\DataAbstractionLayer\PromotionExclusionUpdater instead
    * Deprecated `\Shopware\Core\Checkout\Promotion\DataAbstractionLayer\Indexing\PromotionRedemptionIndexer`, use \Shopware\Core\Checkout\Promotion\DataAbstractionLayer\PromotionRedemptionUpdater instead
    * Deprecated `\Shopware\Core\Content\Category\DataAbstractionLayer\Indexing\BreadcrumbIndexer`, use `\Shopware\Core\Content\Category\DataAbstractionLayer\CategoryBreadcrumbUpdater` instead
    * Deprecated `\Shopware\Core\Content\Media\DataAbstractionLayer\Indexing\MediaFolderConfigIndexer`, use `\Shopware\Core\Content\Media\DataAbstractionLayer\MediaFolderConfigurationIndexer` instead
    * Deprecated `\Shopware\Core\Content\Media\DataAbstractionLayer\Indexing\MediaFolderSizeIndexer`, use `\Shopware\Core\Content\Media\DataAbstractionLayer\MediaFolderConfigurationIndexer` instead
    * Deprecated `\Shopware\Core\Content\Media\DataAbstractionLayer\Indexing\MediaThumbnailIndexer`, use `\Shopware\Core\Content\Media\DataAbstractionLayer\MediaIndexer` instead
    * Deprecated `\Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductCategoryTreeIndexer`, use `\Shopware\Core\Content\Product\DataAbstractionLayer\ProductCategoryDenormalizer` instead
    * Deprecated `\Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductListingPriceIndexer`, use `\Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ListingPriceUpdater` instead
    * Deprecated `\Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductRatingAverageIndexer`, use `\Shopware\Core\Content\Product\DataAbstractionLayer\RatingAverageUpdater` instead
    * Deprecated `\Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductStockIndexer`, use `\Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdater` instead
    * Deprecated `\Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\VariantListingIndexer`, use `\Shopware\Core\Content\Product\DataAbstractionLayer\VariantListingUpdater` instead
    * Deprecated `\Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordIndexer`, use `\Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater` instead
    * Deprecated `\Shopware\Core\Content\ProductStream\DataAbstractionLayer\Indexing\ProductStreamIndexer`, use `\Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexer` instead
    * Deprecated `\Shopware\Core\Content\Rule\DataAbstractionLayer\Indexing\RulePayloadIndexer`, use `\Shopware\Core\Content\Rule\DataAbstractionLayer\RuleIndexer` instead
    * Deprecated `\Shopware\Core\Content\Seo\DataAbstractionLayer\Indexing\SeoUrlIndexer`, use `\Shopware\Core\Content\Seo\SeoUrlUpdater` instead
    * Deprecated `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\Indexer\ChildCountIndexer`, use `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater` instead
    * Deprecated `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\Indexer\InheritanceIndexer`, use `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater` instead
    * Deprecated `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\Indexer\ManyToManyIdFieldIndexer`, use `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater` instead
    * Deprecated `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\Indexer\TreeIndexer`, use `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater` instead
    * Deprecated `\Shopware\Elasticsearch\Framework\Indexing\EntityIndexer`, use `\Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer` instead
    * Deprecated `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface`, use `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer` instead
* Storefront    
    Deprecated `$connection->executeQuery()` for write operations
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
    * Deprecated `\Shopware\Storefront\Theme\Twig\ThemeTemplateFinder` use `TemplateNamespaceHierarchyBuilderInterface` instead
    * Added JS plugin to add a Google Analytics integration: `google-analytics.plugin.js` 
    * Added additional data to the JS plugin events `SearchWidget::handleInputEvent`, `FormValidation::onFormSubmit` and `AddToCart::beforeFormSubmit`
    * Added `\Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory` and deprecated the factory methods of `\Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration` in favor of the new factory class
    * Added `\Shopware\Storefront\Theme\StorefrontPluginRegistryInterface`
    * Added `\Shopware\Storefront\Theme\ThemeFileImporterInterface` which is used by ThemeCompiler to import theme files
    * Added `\Shopware\Storefront\Theme\ThemeLifecycleHandler` to handle theme lifecycle.
    * `\Shopware\Storefront\Theme\Subscriber\PluginLifecycleSubscriber` now uses `\Shopware\Storefront\Theme\ThemeLifecycleHandler`, constructor arguments changed because of that.
    * Added Twig Filter `replace_recursive` for editing values in nested Arrays
    * All javascript plugin options can now be overwritten in Twig
    * Added `Shopware\Storefront\Event\ThemeCompilerEnrichScssVariablesEvent` to be able to add custom SCSS variables.
    * When `Hide products after clearance` is enabled, products marked as on "clearance sale" are hidden, as soon as their stock depletes back to 0

**Removals**

* Administration
    * `common` folder with private packages got removed, the packages are public now and are installed from the NPM registry (see: [https://www.npmjs.com/org/shopware-ag](https://www.npmjs.com/org/shopware-ag))
    * Refactored `sw-newsletter-recipient-list`, it now uses `repositoryFactory` instead of `StateDeprecated` for fetching and editing data
        * Removed `LocalStore`
        * Removed `StateDeprecated`
        * Removed computed `salesChannelStore`
        * Removed computed `tagStore`
        * Removed computed `tagAssociationStore`
    * The component `sw-plugin-box` was refactored to use the `repositoryFactory` instead of `StateDeprecated` to fetch and save data
        * Removed `StateDeprecated`
        * Removed computed `pluginStore`
    * The component `sw-settings-payment-detail` was refactored to use the `repositoryFactory` instead of `StateDeprecated` to fetch and save data
        * Removed `StateDeprecated`
        * Removed computed `paymentMethodStore`
        * Removed computed `ruleStore`
        * Removed computed `mediaStore`
    * `sw-settings-custom-field-set`
        * Removed method which overrides the mixin method `getList`
    * `sw-settings-document-list`
        * Removed method which overrides the mixin method `getList`
    * Refactor  `sw-settings-snippet-list`
        * Removed `StateDeprecated`
        * Removed computed property `snippetSetStore`
    * Refactor `sw-settings-snippet-set-list`
        * Removed `StateDeprecated`
        * Removed computed property `snippetSetStore`
    * Refactor mixin `sw-settings-list.mixin`
        * Removed `StateDeprecated`
        * Removed computed property `store`
    * Refactor the module `sw-settings-number-range-detail`
        * Removed `LocalStore`
        * Removed `StateDeprecated`
        * Removed data `typeCriteria`
        * Removed data `numberRangeSalesChannelsStore`
        * Removed data `numberRangeSalesChannels`
        * Removed data `numberRangeSalesChannelsAssoc`
        * Removed data `salesChannelsTypeCriteria`
        * Removed computed `numberRangeStore`
        * Removed computed `firstSalesChannel`
        * Removed computed `salesChannelAssociationStore`
        * Removed computed `numberRangeStateStore`
        * Removed computed `salesChannelStore`
        * Removed computed `numberRangeTypeStore`
        * Removed method `onChange`
        * Removed method `showOption`
        * Removed method `getPossibleSalesChannels`
        * Removed method `setSalesChannelCriteria`
        * Removed method `enrichAssocStores`
        * Removed method `onChangeSalesChannel`
        * Removed method `configHasSaleschannel`
        * Removed method `selectHasSaleschannel`
        * Removed method `undeleteSaleschannel`
    * Removed watcher on `width` from component `sw-media-add-thumbnail-form`
    * Removed computed property `uploadStore` from component `sw-media-list-selection` 
    * Removed computed property `mediaStore` from component `sw-media-media-item` 
    * Removed computed property `mediaFolderConfigurationThumbnailSizeStore` from component `sw-media-modal-folder-settings`
    * Removed injection of `mediaFolderService` from `sw-media-modal-move`
    * Removed computed property `uploadStore` from component `sw-media-modal-replace` 
    * Removed computed property `mediaItemStore` from component `sw-media-modal-replace` 
    * Removed computed property `uploadStore` from component `sw-media-upload` 
    * Removed computed property `mediaItemStore` from component `sw-media-upload` 
    * Removed computed property `folderStore` from component `sw-media-upload` 
    * Removed computed property `folderConfigurationStore` from component `sw-media-upload` 
    * Removed computed property `thumbnailSizesStore` from component `sw-media-upload` 
    * Removed computed property `uploadStore` from component `sw-duplicated-media` 
    * Removed computed property `uploadStore` from component `sw-upload-store-listener` 
    * Removed computed property `mediaStore` from component `sw-upload-store-listener` 
    * Removed computed property `productStore` from component `sw-media-quickinfo-usage` 
    * Removed computed property `parentFolder` from component `sw-media-breadcrumbs` 
    * Removed data property `done` from component `sw-media-library`
    * Removed computed property `folderLoader` from component `sw-media-library`
    * Removed computed property `mediaLoader` from component `sw-media-library`
    * Removed computed property `uploadStore` from component `sw-media-modal`
    * Removed method `hideSelectedItems` from component `sw-media-modal`
    * Removed method `unhideSelectedItems` from component `sw-media-modal`
    * Removed computed property `mediaItemStore` from component `sw-media-index` 
    * Removed computed property `uploadStore` from component `sw-media-index` 
    * Removed computed property `currentFolder` from component `sw-media-index` 
    * Removed computed property `currentFolderName` from component `sw-media-index` 
    * Removed computed property `parentFolder` from component `sw-media-index` 
    * Removed computed property `parentFolderName` from component `sw-media-index` 
    * Removed computed property `uploadStore` from component `sw-product-media-form` 
    * Removed computed property `uploadStore` from component `sw-product-variants-delivery-media` 
    * Removed computed property `uploadStore` from component `sw-property-option-detail` 
    * Removed computed property `mediaStore` from component `sw-property-option-detail` 
    * CustomFields are now sorted naturally when custom position is used with customFieldPosition (for example 1,9,10 instead of 1,10,9)
    * Fix endless loading spinner in categories when user changes content language without having a category selected
    * Add `rel="noopener"` to all `target="_blank"` links
    * Fix wrong behavior of switch fields, checkboxes and radio fields when clicking on the label

* Core
    *

* Storefront
    * Removed duplicated `StorefrontPluginRegistryInterface` param from `\Shopware\Storefront\Theme\ThemeService`s constructor.
    * Add `rel="noopener"` to all `target="_blank"` links
