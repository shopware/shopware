CHANGELOG for 6.2.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 6.2 minor versions.

To get the diff for a specific change, go to https://github.com/shopware/platform/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/shopware/platform/compare/v6.1.0..v6.2.0
To get the diff between two versions, go to https://github.com/shopware/platform/compare/v6.1.0...6.2

### 6.2.3

**Addition / Changes**

* Administration
    * Added `v-model` attribute to input field in `sw-property-search`
    * Implemented blocks for the different options in the `sw-cms-el-config-product-box` modules `sw-select-field`s.
    This allows appending additional options to the `sw-select-field`s.
        * Added `{% block sw_cms_element_product_box_config_layout_select_options %}`
        * Added `{% block sw_cms_element_product_box_config_displaymode_select_options %}`
        * Added `{% block sw_cms_element_product_box_config_settings_vertical_align_options %}`
    * Added property `placeholderIsPassword` to `sw-password-field` component
    * Added CSP header to the admin page. Inline scripts are now disallowed by default. You have to add a nonce attribute with the value `cspNonce` to authorize inline scripts. 
    * Refactored password confirmation in `sw-profile-index`
        * Deprecated property `oldPassword` use `confirmPassword instead
        * Deprecated method `validateOldPassword`, as it is not necessary anymore
        * Deprecated block `sw_profile_index_password_card_old_password_field` in template
    * Refactored password confirmation in `sw-settings-user-detail`
        * Deprecated methods `onChangePassword`, `onClosePasswordModal`, `onSubmit`, as it is not necessary anymore
        * Deprecated blocks `sw_settings_user_detail_grid_change_password` and `sw_settings_user_detail_content_password_modal in template
    * Added password confirmation to `sw-settings-user-list` for deleting users 

* Core
    * Added `Czech koruna` as currency
    * Added `GuestCustomerRegisterEvent`
    * Changed `\Shopware\Core\Content\ContactForm\SalesChannel\ContactFormRoute` to return empty string instead null.
    * Fixed RetryMessage-mechanism if message handler class was not a public service
    * Added ProductCrossSelling Events
        * Added `CrossSellingProductCriteriaEvent`
        * Added `CrossSellingProductListCriteriaEvent`
        * Added `CrossSellingProductStreamCriteriaEvent`
    * Changed `\Shopware\Core\System\SalesChannel\Api\StructEncoder` to work correctly with aggregations
    * Changed `product.listing_prices` data structure. The new structure will be reindexed by `\Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer` but may take same time to complete
    * Simplified storefront snippet file loading, PHP classes for snippet files aren't necessary anymore.
    * Deprecated the `\Shopware\Core\System\Snippet\Files\SnippetFileInterface` interface, as it is not necessary anymore
    * Fixed `/api/v2/_info/swagger.html`
    * Added configuration `media.enable_url_upload_feature` in `shopware.yaml` to disable the "Upload media via URL" feature
    * Added configuration `media.enable_url_validation` in `shopware.yaml` to disable the URL validation when a media is uploaded via URL
    * Added decoratable class `Shopware\Core\Content\Media\File\FileUrlValidator`
    * Added the following headers to improve security:
        * `Strict-Transport-Security: max-age=31536000; includeSubDomains` if the request is secure (HTTPS)
        * `X-Frame-Options: deny`
        * `X-Content-Type-Options: nosniff`
        * `Content-Security-Policy: script-src 'none'; object-src 'none'; base-uri 'self';` default for requests with route scope other than `administration` or `storefront`

    * Added option `cookie_secure : 'auto'` to `framework.yaml` to secure the session cookie for request over secure connection
    * Enabled `secure` flag for every other used cookies
    * `\Shopware\Storefront\Framework\Csrf\CsrfPlaceholderHandler::replaceCsrfToken` requires now the current request as second function parameter.
* Storefront
    * Added block `component_offcanvas_cart_header_item_counter` to `src/Storefront/Resources/views/storefront/component/checkout/offcanvas-cart.html.twig`
    * Added the `--keep-cache` option to the `http:cache:warm:up` to keep the current cache as warmup target
    * Show required asterisk on telephone number field if the field is required in registration form.
    * Added request attribute with key `\Shopware\Storefront\Framework\Routing\RequestTransformer::STOREFRONT_URL` for the base url of the storefront. It contains scheme, host, port, sub directory of the web root and the virtual path. Example: http://localhost:8000/subdir/de
    * Fixed urls in emails for shops with virtual paths like /de
    * Added `GenericPageLoaderInterface` to `CheckoutConfirmPageLoader`
    * Removed headers `sw-version-id`, `sw-context-token` and `sw-language-id` from Storefront response.

**Removals**

* Administration
    * Disallow adding script with `document.write` due to new CSP header. Use `document.createElement('script')` and `element.appendChild` instead.

* Core

* Storefront

### 6.2.2

**Addition / Changes**

* Storefront
    * Fixed multiple inheritance for style and script files
    
### 6.2.1

**Addition / Changes**

* Administration
    * Added property `popoverClasses` to `sw-select-result-list` and `sw-single-select`
    * Fixed broken promotion exclusion configuration in `sw-promotion-basic-form`
    * Fixed positioning mixin for more than 25 entries
    * Added twig blocks to the `sw-product-detail` template for the sidebar and sidebar-items
        * `sw_product_detail_sidebar`
        * `sw_product_detail_sidebar_additional_items`
    * Fixed snippet merging when plugins override existing snippets, which already exist in that specific locale 
 
    * Fixed the administration multi-inheritance if a component was overriden and extended by multiple components
    * Added new filterable field to `sw-property-list` and `sw-property-filter`
    * Added string for `packUnitPlural` to `sw-product-stream/snippet/en-EN.json` and `sw-product-stream/snippet/de-DE.json` 
        * Added `packUnitPlural`
    * Added strings for `packUnitPlural` to `sw-product/snippet/en-GB.json` and `sw-product/snippet/de-DE.json` 
        * Added `placeholderPackUnitPlural`
        * Added `labelPackUnitPlural`
        * Added `packUnitPluralHelpText`
    * Added translated `packUnitPlural` field to `sw-product-packaging-form`
    * Added `packUnitPlural` to `entity-schema.mock.js`
    * Added block `sw_product_packaging_form_height_field` to `sw-product-packaging-form`
    * Price input fields substitute commas with dots automatically in Add Product page.
    * Added a link to the customer name in the order overview. With this it is now possible to open the customer directly from the overview.
    * Added property `fileAccept` to 
        * `sw-media-upload-v2`
        * `sw-media-compact-upload-v2`
        * `sw-media-modal-v2`
        * `sw-media-index`
    * Change default value of `accept` in `sw-media-index` to `*/*` to allow all types of files in media management 
    * Added config option for disabling reviews in the storefront
    * Fixed the displaying of the media filename in Media Library grid in case it only contains digits
    * Added tooltips to the toolbar of text editor
    * Added isInlineEdit property to component `sw-text-editor-toolbar`
    * Added language switch to Scale Units list page to translate scale units
    * Added `zIndex` prop on `sw-context-button` component, to allow overriding the default z-index
    * Fix timezone of `orderDate` in order grid
    * Added image lazy loading capability to the `ZoomModalPlugin` which allows to load images only if the zoom modal was opened

* Core
    * Added new `PromotionNotFoundError` and `PromotionNotEligibleError` errors to the cart if a promotion couldn't be added
    * Added ability to show/hide properties from product filter panel in product listing
    * Added protected `pack_unit_plural` to `Migration1536233120Product.php`
    * Added protected `packUnitPlural` to `ProductTranslationEntity.php`
    * Added StringField `packUnitPlural` to `ProductTranslationDefinition.php`
    * Added protected `packUnitPlural` to `ProductEntity.php`
    * Added TranslatedField `packUnitPlural` to `ProductDefinition.php`
    * `SystemConfigService::get` will now return the value that was set with `SystemConfigService::set`. Now when a `0` is set, a `0` will be the returned with `get` instead of `null`.
    * Added `\Shopware\Core\Content\Product\Exception\ReviewNotActiveException` exception
        * This exception is thrown if the review routes are called if reviews are disabled
    * Fixed a type error in the `\Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageRepository` in a sorting closure
    * Added possibility to delete orders without documents on `sw-order-list`
    * Added methods `isProductGroup` `setIsProductGroup` `isVariantProduct` in `\Shopware\Core\Content\Product\ProductEntity` 
    * DB level write operation (e.g. cascade deletes) are not validated against the write prtoection anymore
    * Changed exit code from command `es:index` to 0
    * Added support for nullable values for MultiInsertQueryQueue
 
* Storefront
    * Added `pack_unit_plural` to `buy-widget-form.html.twig`
    * The `ThemeFileResolver` doesn't produce duplicates if you have a theme that inherits from `@Storefront` and contains `@Plugins` (NEXT-8435)
    * Removed `required` status for `firstName` and `lastName`on `newsletter-form.html.twig`
    * Added fallback for missing `getFirstName` and `getLastName` on `NewsletterRegisterEvent.php`
    * Added new Twig blocks in `src/Storefront/Resources/views/storefront/page/account/order/index.html.twig`
        * page_checkout_aside_actions_csrf
        * page_checkout_aside_actions_payment_method_id
        * page_checkout_confirm_form_submit
    * Added JS plugins `FormCsrfHandler` and `FormPreserver` to the `<form>` element in `src/Storefront/Resources/views/storefront/page/account/order/index.html.twig`
    * Removed alphanumeric filter product numbers in the quick add action
    * If all variants are expanded in the listing display the buy button
    * Fixed mail headers and footers not being properly attached to mails when sending mails from the administration
    * Excluded Promotions will now be handled in `PromotionCalculator` and `PromotionDeliveryCalculator` instead of `PromotionCalculator`
    * The storefront session is now invalidated after logout. This can be configured in `Settings -> Login / Registration`
    * Enabled the Slugify TwigFilter extension
    * Added new events to be able to extend the requests which are used for the Store-API calls. Use them to pass request parameters from the original storefront request to the new request for the Store-API call
        * `CancelOrderRouteRequestEvent`
        * `CurrencyRouteRequestEvent`
        * `HandlePaymentMethodRouteRequestEvent`
        * `LanguageRouteRequestEvent`
        * `OrderRouteRequestEvent`
        * `PaymentMethodRouteRequestEvent`
        * `SalutationRouteRequestEvent`
        * `SetPaymentOrderRouteRequestEvent`
        * `ShippingMethodRouteRequestEvent`


### 6.2.0

**Addition / Changes**

* Administration
    * Added `onDuplicate` to `sw-product-detail` and `sw-product-list`
    * Added Overwrites parameter to `clone` in `repository.data.js`. Overwrites contain entity field which should be overwritten with the given data. Example in `sw-product-detail` -> `onDuplicate`
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
    * Added custom `slot` to `sw-radio-field` component
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
    * Changed sw-text-editor to ignore addProtocol when the domainPlaceholder is used as a link
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
        * The `delete` method now throws an exception when the delete request is not successful
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
    * Added `sw-order-create` page, `sw-order-create-base` view, and `create` route to `sw-order` module
    * Added order state in `sw-order` module
    * Added `cart-sales-channel.api.service` to handle cart line item services in create order page
    * Added `check-out-sales-channel.api.service` to handle save order service in create order page
    * Added component `sw-order-create-details-header` handle customer selection in create order page 
    * Added component `sw-order-create-details-body` to handler customer contact information in create order page 
    * Added component `sw-order-create-details-footer` to handle sales channel context in create order page
    * Added component `sw-order-new-customer-modal` to create new customer
    * Added component `sw-order-create-address-modal` to create new address of selected customer in create order page
    * Added component `sw-order-line-items-grid-sales-channel` which can be used to display line items list in create order page
    * Added component `sw-order-create-promotion-modal` which can be used to display and disable the automatic promotions
    * Refactor `sw-order-product-select`
        * Deprecated `displayProductSelection` prop. It will be removed with version 6.4
        * Added `inheritance: true` in context of `productRepository` in `sw-order-product-select`
    * Refactor `sw-order-line-items-grid`
              * Deprecated `isItemCredit` prop. It will be removed with version 6.4
              * Removed `slot` and `slot-scope` attribute in favor of new `v-slot` directive
    * Added component `sw-order-promotion-tag-input` to handle showing promotion code list, entering and removing promotion code
    * Added component `sw-order-create-invalid-promotion-modal` to show recent invalid promotion codes after clicking on Save Order button
    * Fixed error of showing shipping cost value in `sw-order-detail-base` when order detail has shipping cost discount
    * Refactor `sw-order-savable-field`, changed style and position of Save button and Cancel button
    " Added `slice` in `array` of `utils.service`
    * Fixed hover style of `sw-label`
    * Added an error notification for user when he deletes a customer group that has a SalesChannel and/or a customer assigned to it.
    * Added `bulk-modal-cancel`, `bulk-modal-delete-items`, `delete-modal-cancel` and `delete-modal-delete-item` slots to `sw-entity-listing.html.twig`
    * Added twig blocks `sw_cms_page_form_section_empty_state_block_text` and `sw_cms_page_form_section_empty_state_block` to `sw-cms-page-form.html.twig`
    * The `fixed` directive is now deprecated and will be removed with version 6.4
    * Ordered settings items on settings list index page alphabetically
    * Show error when theme compiling in theme manager throws an error
    * Moved "Customer Group" settings-item from settings-index page to navigation sidebar
    * Moved "Salutation" settings-item from settings-index page to navigation sidebar
    * Add automatic versions to HttpClient. You can override the default version in the config argument
    * Add `Hide products after clearance` option in `Setting -> Shop -> Listing`
    * Add `Product listings` tab in the `Storefront presentation` modal to configure the variant preselection
    * Updated Node Dependencies
    * Added new `sw-settings-captcha-select` component
        * This component allows users to define active captchas via `Settings -> Basic information`
    * Fix renaming of duplicated media names
    * Added new blocks in `sw-settings-user-detail` to allow overriding each card by its own:
        * `sw_settings_user_detail_content_inner` to override card view only
        * `sw_setting_user_detail_card_basic_information` to override basic information
        * `sw_setting_user_detail_card_integrations` to override integration settings
    * Fixed a bug where the pages content could not be overridden because `sw_settings_user_detail_content` existed twice
    * Fixed a bug in `sw-profile-index` that caused media requests to fail
    * Added new component `sw-text-preview` to display an expandable preview of a text. It will show a "Read more" button if the text has a certain length. On click a modal shows the whole text.
    * Fixed a bug in `sw-description-list`, added `display: grid` CSS property to the component and changed default of `grid` property to `1fr` to ensure usages of this component have the same behaviour
    * Added `sw-import-export-activity-detail` component
    * Added new condition-type `sw-condition-line-item-dimension-height`
    * Added new condition-type `sw-condition-line-item-dimension-width`
    * Added new condition-type `sw-condition-line-item-dimension-length`
    * Added new condition-type `sw-condition-line-item-custom-field`
        * Added new method `getOperatorSetByComponent` in `rule-condition.service.js`
        * Added new property `customFields` to `entityBlacklist` in `product-stream-condition.service.js`
        * Added new condition `cartLineItemCustomField` in `condition-type-data-provider.decorator.js`
    * Added support of module favicons from plugins, set the `faviconSrc` prop of your module to the name of your bundle in the public bundles folder.   
    * Added `media-upload-cancel` to `media.api.service`
    * Fixed a bug in `sw-duplicated-media-v2` to reload media list when user clicked to cancel
    * Fixed a bug in `sw-sales-channel-detail-base` for IP whitelist on maintenance mode on new sales channel 
    * Added mapping validation for import/export profiles
    * Added improved error handling in importer and exporter
    * Fixed a bug in `sw-media-quickinfo-usage` to show media in used information
    * Added prop `routerLinkTarget` attribute to `sw-media-quickinfo-usage` for can set `target` options in `<router-link>`
    * `sw-media-modal-delete` now shows where media is used
    * For batch delete `sw-media-modal-delete` shows all used media entities
    * Fixed `entity-hydrator.data.js` to check `row` parameter exist data
    * Added `sw_order_detail_actions_slot_smart_bar_actions` block to `sw-order/page/sw-order-detail/sw-order-detail.html.twig`
    * Fixes missing snippets in deleting cache notifications
    * Added block `sw_settings_content_card_content` to `sw-settings-index` to override the content of the settings card
    * Fixed variants name in cross selling preview listing
    * Added `rawUrl` Twig function
    * The SalesChannel url is now available in every mail template
    * Fixed after order link in the following mail templates:
        * `order_confirmation_mail`
        * `order_delivery.state.cancelled`
        * `order_delivery.state.returned`
        * `order_delivery.state.shipped_partially`
        * `order_delivery.state.shipped`
        * `order_delivery.state.returned_partially`
        * `order.state.cancelled`
        * `order.state.open`
        * `order.state.in_progress`
        * `order.state.completed`
        * `order_transaction.state.refunded_partially`
        * `order_transaction.state.reminded`
        * `order_transaction.state.open`
        * `order_transaction.state.paid`
        * `order_transaction.state.cancelled`
        * `order_transaction.state.refunded`
        * `order_transaction.state.paid_partially`
    * If you edited one of these mail templates you need to add the `rawUrl` function manually like this: `{{ rawUrl('frontend.account.edit-order.page', { 'orderId': order.id }, salesChannel.domain|first.url) }}` 
* Core    
    * Added support of module favicons from plugins, set the `faviconSrc` prop of your module to the name of your bundle in the public bundles folder.
    * Set `crossSellingAssignedProducts` and `tags` to `CascadeDelete` in `ProductDefinition`
    * The `clone` method of the `ApiController` now passes overwrites to the `EntityRepository`
    * The `clone` method of the `VersionManager` now accepts `overwrites` and combines the overwrites with the cloned data using `array_replace_recursive`.
    * Added variant preselection logic
        * Added `Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader` to handle product variant preselection
        * Moved the available stock and display group filters from  
        `Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber` to the new 
        `Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader`
        * These classes use the new `ProductListingLoader` instead of querying over the `SalesChannelRepositoryInterface` for products directly 
            * `Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute`
            * `Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRoute`
            * `Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRoute`
    * The `Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter` no longer supports `||` and `&&`.
    * The usage of `entity` in the `shopware.entity.definition` tag is deprecated and will be removed with 6.4. 
    * Added `SalesChannelAnalyticsEntity` to define the Google Analytics configuration
    * Deprecated `\Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextWithHtmlField`, use `\Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField` with `AllowHtml` flag instead
    * Added `length`, `width`, `height` variables to `\Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation`  
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
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemPromotedRule` to check if a promoted product is in cart
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemInCategoryRule` to check product categories in cart 
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemTaxationRule` to check specific taxation in cart 
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemDimensionWidthRule` to check the width of a product in cart
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemDimensionHeightRule` to check the height of a product in cart
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemDimensionLengthRule` to check the length of a product in cart
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemDimensionWeigthRule` to check the weight of a product in cart
    * Added new `Shopware\Core\Checkout\Cart\Rule\LineItemListPriceRule` to check if a product with a specific list price is in cart
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
    * Added `novelty` rule builder condition-type
    * Added OrderTransactionStates `failed` and `in_progress`
    * Deprecated `OrderTransactionStateHandler::pay` use `OrderTransactionStateHandler::doPay` instead
    * Deprecated Action Constant `StateMachineTransitionActions::PAY` use `StateMachineTransitionActions::DO_PAY` instead
    * Deprecated route `_action/theme/{themeId}/fields`, use `_action/theme/{themeId}/structured-fields` instead
    * Added new route `_action/theme/{themeId}/structured-fields`
    * Added new `Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts` to provide the possibility to assign individual products to cross selling
    * Added new `\Shopware\Core\Framework\Plugin\BundleConfigGenerator` to generate webpack bundle config and moved the according logic from `\Shopware\Core\Framework\Plugin\BundleConfigDumper` to the new class
    * Added methods `cancelOder` and `setPaymentMethod` in `Shopware\Core\Checkout\Order\SalesChannel\OrderService`
    * Added methods `cancelOrder` and `setPaymentMethod` in `Shopware\Core\Checkout\Order\SalesChannel\OrderService`
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
    * Added new `\Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute` to register a new customer with the new api route `/store-api/v1/account/register`
    * Added new `\Shopware\Core\Checkout\Customer\SalesChannel\RegisterConfirmRoute` to confirm a double optin registration with the new api route `/store-api/v1/account/register-confirm`
    * Added new `\Shopware\Core\Checkout\Order\SalesChannel\CancelOrderRoute` to cancel a order with the new api route `/store-api/v1/order/state/cancel`
    * Added new `\Shopware\Core\Checkout\Order\SalesChannel\SetPaymentOrderRoute` to change the payment method of a order with the new api route `/store-api/v1/order/set-payment`
    * Added `\Shopware\Core\Framework\Api\Converter\DefaultApiConverter` to handle deprecated fields from DAL in the api versions
        * When the new field and the old field is send, the converter will prefer the new field
        * Added new header `sw-ignore-deprecations` to ignore deprecations and receive all fields
        * This header is used now in all api calls in the administration
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
    * Fixed a bug when the criteria contains a list of ids and no sortings, queries or a term, the search result will be sorted by the provided ids
    * Added new route `/api/v{version}/_action/container_cache` which clears the Symfony Container cache
    * Added `customerComment` property to the `Shopware\Core\Checkout\Order\OrderEntity.php`
    * Added `page_checkout_confirm_shipping_invalid_tooltip`
    * Changed level of `ShippingMethodBlockedError` from `LEVEL_ERROR` to `LEVEL_WARNING`
    * Added `CheckoutConfirmControllerTest`
    * Added `BLUE_GREEN_DEPLOYMENT` environment variable
    * `bin/setup` asks if you want to enable blue/green deployment
    * Removed custom cache from `\Shopware\Storefront\Theme\ThemeService` to fix http cache invalidation issues
    * Marked `\Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface` as internal
    * Added `\Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface::sync` function
    * Added `single-operation` header in `_action/sync` endpoint
    * Added `errorUrl` to `\Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct` to define an explicit redirect for failed payments
    * Added `exception` to `\Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct` to provide the thrown exception for calling Instances of `\Shopware\Core\Checkout\Payment\PaymentService::finalizeTransaction`
    * Added `errorUrl` to `\Shopware\Core\Checkout\Payment\Cart\PaymentTransactionChainProcessor::process` to provide the errorUrl for the TokenStruct
    * Deprecated `\Shopware\Core\Checkout\Payment\Cart\Token\JWTFactory` use `\Shopware\Core\Checkout\Payment\Cart\Token\JWTFactoryV2` instead
    * Deprecated `\Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterface` use `\Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterfaceV2` instead
    * Added new Field `afterOrderEnabled` to `\Shopware\Core\Checkout\Payment\PaymentMethodDefinition`
    * Added `\Shopware\Core\Framework\Plugin\Requirement\RequirementsValidator::resolveActiveDependants` method
    * Added `\Shopware\Core\Framework\Plugin\Exception\PluginHasActiveDependantsException` exception
        * This exception is now thrown before a plugin which other plugins depend on is deactivated
    * Added a new translatable `label` property to `\Shopware\Core\Content\ImportExport\ImportExportProfileDefinition`
    * Marked `name` property of `\Shopware\Core\Content\ImportExport\ImportExportProfileDefinition` as nullable 
    * Added possibility to write all sync operation in a single transaction by providing the `single-operation` header
    * Added possibility to move dal indexing to message queue when using the sync api by providing the `indexing-behavior` header
    * Deprecated `sort` parameter for product listing, search and suggest gateway, use `order` instead
    * Deprecated `\Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder::getAllowedLimits`
    * Deprecated `shopware.api.allowed_limits` configuration
    * Added `definition` parameter in `\Shopware\Elasticsearch\Framework\ElasticsearchHelper::addTerm`
    * Deprecated `\Shopware\Storefront\Controller\SearchController::pagelet`, use `\Shopware\Storefront\Controller\SearchController::ajax` instead
    * Deprecated `widgets.search.pagelet` route, use `widgets.search.pagelet.v2` instead
    * Added `definition` parameter in `\Shopware\Elasticsearch\Framework\ElasticsearchHelper::addTerm` 
    * Allow additional sorting after the `_score` sorting when using a search term or score query in `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria`

* Storefront
    * Added `\Shopware\Core\Framework\Api\Controller\CaptchaController` which provides a list of all available captchas to the administration
    * Added new `\Shopware\Core\Checkout\Cart\Rule\LineItemCustomFieldRule` to check available attributes in cart 
    * Deprecated `$connection->executeQuery()` for write operations
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
    * Added `\Shopware\Storefront\Theme\ThemeFileImporterInterface` which is used by `ThemeCompiler`, `ThemeFileResolver` and `ThemeLifecycleService` to import theme files
    * Added `\Shopware\Storefront\Theme\ThemeLifecycleHandler` to handle theme lifecycle.
    * `\Shopware\Storefront\Theme\Subscriber\PluginLifecycleSubscriber` now uses `\Shopware\Storefront\Theme\ThemeLifecycleHandler`, constructor arguments changed because of that.
    * Added Twig Filter `replace_recursive` for editing values in nested Arrays
    * All javascript plugin options can now be overwritten in Twig
    * Added `Shopware\Storefront\Event\ThemeCompilerEnrichScssVariablesEvent` to be able to add custom SCSS variables.
    * When `Hide products after clearance` is enabled, products marked as on "clearance sale" are hidden, as soon as their stock depletes back to 0
    * We have removed the fallback mechanism of `theme.json` for the `views` array. If `@Storefront` or `@Plugins` are not defined in the `views` array, they will not be added automatically.
    * It is now possible to inherited several themes from each other. Themes that are not defined in the `views` array of the active theme are excluded from template inheritance.
    * Added `\Shopware\Storefront\Framework\Captcha\Annotation\Captcha` annotation to mark storefront routes which require a captcha check
    * Added `\Shopware\Storefront\Framework\Captcha\AbstractCaptcha` as a base class for captchas
        * Added `\Shopware\Storefront\Framework\Captcha\HoneypotCaptcha`
    * Added `\Shopware\Storefront\Framework\Captcha\Exception\CaptchaInvalidException`
    * Added `\Shopware\Storefront\Framework\Captcha\CaptchaRouteListener` on `KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE`
    * Added base template for captchas at `platform/src/Storefront/Resources/views/storefront/component/captcha/base.html.twig`
        * Added honeypot captcha template at `platform/src/Storefront/Resources/views/storefront/component/captcha/honeypot.html.twig`
    * Update Babel compiler to support "> 1%, IE 11, not dead"
    * Non ES5 modules are now compiled with babel to support IE11
    * Terser Minifier works now in ES5 for better IE11 support
    * Add babel polyfill for IE11
    * Added `rel="noopener"` to all `target="_blank"` links
    * Add polyfill for object fit for IE11
    * All javascript plugin options can now be overwritten in Twig 
    * Added `Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoader`
    * Added `Shopware\Storefront\Page\Account\Order\AccountEditOrderPage`
    * Added `Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent`
    * Deprecated `page_checkout_confirm_payment_invalid_tooltip` twig block
    * Deprecated `page_checkout_confirm_shipping_invalid_tooltip` twig block
    * Added Javascript plugin `form-preserver.plugin.js` to preserve entered values of a form. Add the data attribute `data-form-preserver="true"` to your form to enable the plugin. It will preserve the entered values in the local storage, and restore the values after a page reload. Once the form is submitted, the values are cleared from the storage.
    * Added `\Shopware\Storefront\Theme\ThemeCompilerInterface`
    * Fixed a bug that html purifier config could be overriden for future calls to `sw_sanitize`
    * Added bundle configuration to `HTMLPurifier`s behaviour
        * `storefront.htmlPurifier.cacheDir`: Directory to write `HTMLPurifier` cache (defaults to `kernel.cache_dir`)
        * `storefront.htmlPurifier.enableCache`: Boolean to turn `HTMLPurifier`s cache cache on or off (defaults to `true`)
    * Deprecated `sort` parameter for product listing, search and suggest gateway, use `order` instead
    * Added block `document_line_item_table_iterator` to `@Framework\documents\base.html.twig` to override the lineItem iterator
    * Added `StoreApiClient` which allows to send requests to `store-api` and `sales-channel-api` routes.
    * Theme configuration now allows zero as a value when overriding
    * Changed type of `GenericPageLoader` to `GenericPageLoaderInterface` in `AccountEditOrderPageLoader`
    * Deprecated block `page_product_detail_price_unit_refrence_content` in `buy-widget-price.html.twig`, use `page_product_detail_price_unit_reference_content` instead
    * Fix wrong behavior in `AddToCartPlugin` if user clicks on add to cart button before the js plugin is completely loaded

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
    * Moved bearerAuth location from localStorage to Cookies 
    * Removed `v-fixed` directive in `sw-entity-single-select` of `sw-order-product-select`
* Storefront
    * Removed duplicated `StorefrontPluginRegistryInterface` param from `\Shopware\Storefront\Theme\ThemeService`s constructor
    * Removed duplicated `StorefrontPluginRegistryInterface` param from `\Shopware\Storefront\Theme\ThemeService`s constructor.
    * Add `rel="noopener"` to all `target="_blank"` links
    * Deprecated `layout_header_minimal_switch` in `src/Storefront/Resources/views/storefront/layout/header/header-minimal.html.twig`
    * Deprecated `page_account_overview_newest_order_table_header` in `src/Storefront/Resources/views/storefront/page/account/index.html.twig`
    * Deprecated `page_account_overview_newest_order_table_header_date` in `src/Storefront/Resources/views/storefront/page/account/index.html.twig`
    * Deprecated `page_account_overview_newest_order_table_header_number` in `src/Storefront/Resources/views/storefront/page/account/index.html.twig`
    * Deprecated `page_account_overview_newest_order_table_header_payment_method` in `src/Storefront/Resources/views/storefront/page/account/index.html.twig`
    * Deprecated `page_account_overview_newest_order_table_header_shipping_method` in `src/Storefront/Resources/views/storefront/page/account/index.html.twig`
    * Deprecated `page_account_overview_newest_order_table_header_actions` in `src/Storefront/Resources/views/storefront/page/account/index.html.twig`
    * Deprecated `page_account_orders_table_header` in `src/Storefront/Resources/views/storefront/page/account/order-history/index.html.twig`
    * Deprecated `page_account_orders_table_header_date` in `src/Storefront/Resources/views/storefront/page/account/order-history/index.html.twig`
    * Deprecated `page_account_orders_table_header_number` in `src/Storefront/Resources/views/storefront/page/account/order-history/index.html.twig`
    * Deprecated `page_account_orders_table_header_payment_method` in `src/Storefront/Resources/views/storefront/page/account/order-history/index.html.twig`
    * Deprecated `page_account_orders_table_header_shipping_method` in `src/Storefront/Resources/views/storefront/page/account/order-history/index.html.twig`
    * Deprecated `page_account_orders_table_header_actions` in `src/Storefront/Resources/views/storefront/page/account/order-history/index.html.twig`
    * Deprecated `page_account_order_item_detail_action` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-detail-list.html.twig`
    * Deprecated `page_account_order_item_detail_reorder` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-detail-list.html.twig`
    * Deprecated `page_account_order_item_detail_reorder_form_action` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-detail-list.html.twig`
    * Deprecated `page_account_order_item_detail_reorder_csrf` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-detail-list.html.twig`
    * Deprecated `page_account_order_item_detail_reorder_redirect_input` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-detail-list.html.twig`
    * Deprecated `page_account_order_item_detail_reorder_lineitems_input` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-detail-list.html.twig`
    * Deprecated `page_account_order_item_detail_reorder_lineitem_input` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-detail-list.html.twig`
    * Deprecated `page_account_order_item_detail_reorder_button` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-detail-list.html.twig`
    * Deprecated `page_account_order_item_date` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-item.html.twig`
    * Deprecated `page_account_order_item_date_label` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-item.html.twig`
    * Deprecated `page_account_order_item_date_value` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-item.html.twig`
    * Deprecated `page_account_order_item_number` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-item.html.twig`
    * Deprecated `page_account_order_item_number_label` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-item.html.twig`
    * Deprecated `page_account_order_item_number_value` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-item.html.twig`
    * Deprecated `page_account_order_item_payment_method` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-item.html.twig`
    * Deprecated `page_account_order_item_payment_method_label` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-item.html.twig`
    * Deprecated `page_account_order_item_payment_method_value` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-item.html.twig`
    * Deprecated `page_account_order_item_shipping_method` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-item.html.twig`
    * Deprecated `page_account_order_item_shipping_method_label` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-item.html.twig`
    * Deprecated `page_account_order_item_shipping_method_value` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-item.html.twig`
    * Deprecated `page_account_order_item_actions` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-item.html.twig`
    * Deprecated `page_account_order_item_actions_value` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-item.html.twig`
    * Deprecated `page_account_order_item_actions_value_text` in `src/Storefront/Resources/views/storefront/page/account/order-history/order-item.html.twig`
    * Deprecated `page_checkout_confirm_payment_form` in `src/Storefront/Resources/views/storefront/page/checkout/confirm/confirm-payment.html.twig`
    * Deprecated `page_checkout_confirm_payment_form_csrf` in `src/Storefront/Resources/views/storefront/page/checkout/confirm/confirm-payment.html.twig`
    * Deprecated `page_checkout_confirm_payment_form_redirect` in `src/Storefront/Resources/views/storefront/page/checkout/confirm/confirm-payment.html.twig`
    * Deprecated `page_checkout_confirm_payment_form_fields` in `src/Storefront/Resources/views/storefront/page/checkout/confirm/confirm-payment.html.twig`
    * Deprecated `page_checkout_confirm_payment_form_submit` in `src/Storefront/Resources/views/storefront/page/checkout/confirm/confirm-payment.html.twig`
    * Deprecated `page_checkout_confirm_payment_cancel` in `src/Storefront/Resources/views/storefront/page/checkout/confirm/confirm-payment.html.twig`
    * Deprecated `window.accessKey` and `window.contextToken`, the variables contains now an empty string
    * Removed `HttpClient()` constructor parameters in `src/Storefront/Resources/app/storefront/src/service/http-client.service.js`


### 6.2.1

**Addition / Changes**

* Administration
    * Added `zIndex` prop on `sw-context-button` component, to allow overriding the default z-index
    * Fix timezone of `orderDate` in ordergrid
    * Added image lazy loading capability to the `ZoomModalPlugin` which allows to load images only if the zoom modal was opened
    * Fix wrong behavior in `AddToCartPlugin` if user clicks on add to cart button before the js plugin is completely loaded
    
