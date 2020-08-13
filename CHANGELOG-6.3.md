CHANGELOG for 6.3.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 6.3 minor versions.

To get the diff for a specific change, go to https://github.com/shopware/platform/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/shopware/platform/compare/6.2...master

Table of contents
----------------
* [Table of contents](#table-of-contents)
* [NEXT](#NEXT)
* [6.3.0.0](#630)
  - [Administration](#administration)
  - [Core](#core)
  - [Storefront](#storefront)
  
NEXT
----------------

#### Administration
* Added new privileges service: 
`src/Administration/Resources/app/administration/src/module/sw-property/acl/index.js`
* Added prop `savePermission` to `sw-language-switch/index.js`
* Added new computed props to `sw-property-option-list/index.js`
    * `allowInlineEdit`
    * `tooltipAdd`
    * `disableAddButton`
* Extended `sw-category-detail-base/index.js`
    * Added lifecycle hook `created`
    * Added methods `createdComponent` and `loadProductStreamPreview`
    * Added computed props `productStreamRepository`, `productAssignmentTypes` and `productStreamInvalidError`
    * Added data props `productStreamFilter` and `productStreamInvalid`
    * Extend `mapPropertyErrors` with props `productStreamId` and `productAssignmentType`
* Added prop `plainAppearance` to `sw-data-grid` which provides an alternative and more minimalistic design
* Added prop `absolute` to `sw-empty-state` which is `true` by default
* Added prop `showDescription` to `sw-empty-state` which is `true` by default
* Added prop `selectLabel` to `sw-many-to-many-assignment-card` in order to show a label for the default select element
* Added slot `prepend-select` to `sw-many-to-many-assignment-card` in order to show additional content before the select element
* Added new event `paginate` to `paginateGrid` method in `sw-many-to-many-assignment-card`
* Added slot `select` to `sw-many-to-many-assignment-card` in order to override the default select element
* Added slot `data-grid` to `sw-many-to-many-assignment-card` in order to override the data grid component
* Added new component `sw-product-stream-grid-preview` which displays a product stream preview inside a `sw-data-grid`
* Added support for custom field set selection to `sw-custom-field-set-renderer`
    * Added property `showCustomFieldSetSelection`
* Added support for inheritance to `sw-custom-field-set-renderer`
    * Added property `parentEntity`
* Added ACL permissions to categories module
* Added property `disabled` to `sw-many-to-many-assignment-card` component
* Added property `disabled` to `sw-media-upload-v2` component
* Added property `contextMenuTooltipText` to `sw-tree-item` component
* Added property `allowNewCategories` to `sw-tree-item` component
* Added property `allowDeleteCategories` to `sw-tree-item` component
* Added property `allowDeleteCategories` to `sw-tree` component
* Added property `allowEdit` to `sw-category-tree` component
* Added property `allowCreate` to `sw-category-tree` component
* Added property `allowDelete` to `sw-category-tree` component
* Added computed `contextMenuTooltipText` to `sw-category-tree` component
* Added property `disabled` to `sw-cms-list-item` component
* Added method `onItemClick` to `sw-cms-list-item` component
* Added property `disabled` to `sw-seo-url` component
* Deprecated block `sw_product_detail_properties_empty_state_text_empty` in `sw-product-detail-properties` component.
* Added prop `salesChannelId` to `sw-order-line-items-grid-sales-channel/index.js`
* Added prop `salesChannelId` to `sw-order-product-select/index.js`
* Changed tax id of newly generated variants to null in order to inherit from the parent product
* Fixed template factory so it is possible again to override nested blocks in one `Component.override()`

#### Core

* Changed `keyword` fields in Elasticsearch to normalize to lower case
* Changed temporary filename of sitemap to avoid conflicts with other installations
* Removed required flag of customer_id
* Added `Logger` to `Shopware\Elasticsearch\Framework\ClientFactory::createClient`
* Added event `GenericPageLoadedEvent`, which is fired once a page is requested via the `GenericPageLoader`
* Changed the way the `CheckoutConfirmPage` is loaded. It now uses the `GenericPageLoader` as well.
* Deprecated the constructor of `Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage`. Use `CheckoutConfirmPage::createFrom` instead.
* Added fields in `src/Core/Content/Category/CategoryDefinition.php`
    * `StringField` with `product_assignment_type`
    * `FkField` with `product_stream_id`
    * `ManyToOneAssociationField` with `product_stream_id`
    * Extend defaults with `productAssignmentType` type `product`
* Added new constants in `src/Core/Content/Category/CategoryDefinition.php`
    * `PRODUCT_ASSIGNMENT_TYPE_PRODUCT` with value `product`
    * `PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM` with value `product_stream`
* Added new methods in `src/Core/Content/ProductStream/ProductStreamEntity.php`
    * `getCategories`
    * `setCategories`
* Added new methods in `src/Core/Content/Category/CategoryEntity.php`
    * `getProductStream`
    * `setProductStream`
    * `getProductStreamId`
    * `setProductStreamId`
    * `getProductAssignmentType`
    * `setProductAssignmentType`
* Added migration `src/Core/Migration/Migration1592837424AddProductTypeToCategory.php`
* Added `OneToManyAssociationField` with `categories` and `product_stream_id` in `src/Core/Content/ProductStream/ProductStreamDefinition.php`
* Added arguments `categoryRepository` and `productStreamBuilder` to `src/Core/Content/Product/SalesChannel/Listing/ProductListingRoute.php`
    * Added arguments `category.repository` and `Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder` to service `Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute` in `src/Core/Content/DependencyInjection/product.xml`
* Deprecated `.php_cs.dist` cs-fixer config file. Use Easy Coding Standard instead.
* Plugins that are not installed can't be updated anymore. If you try to update an plugin that is not yet installed with `bin/console plugin:update` the plugin will be skipped.
* Added bool `custom_field_set_selection_active` to ProductDefinition
* Added many to many association `customFieldSets` to ProductDefinition
* Added new entity `product_custom_field_set`
* Added possibility to use a write operation without actual data, which then does nothing
* Added generation of order delivery positions when editing an order in the administration
* Changed the way `senderEmail` is resolved in `\Shopware\Core\Content\MailTemplate\Service\MailService`. It's now possible to override it with `$data['senderEmail']`. 
* Thumbnails are no longer being upscaled when the original image is smaller than the desired thumbnail size
* Added new constant `DISPLAY_TYPE_MEDIA` in `Shopware\Core\Content\Property\PropertyGroupDefinition`

#### Storefront

* Added new plugin class `clear-input.plugin.js`
* Added new event methods `onOpenButtonClick`, `onCloseButtonClick` and `onClearButtonClick` in `date-picker.plugin.js`
* Added new method `registerEventListeners` in `date-picker.plugin.js`
* Added new property `selectors` to `static options` in `date-picker.plugin.js` with the following values:
   * `openButton: null`
   * `closeButton: null`
   * `clearButton: null`
* We extended setup of the `storefront:hot-proxy`
    * The proxy's assets port is now configurable.
        * Using npm: run `APP_URL="<your url>" STOREFRONT_ASSETS_PORT=<some port> PROJECT_ROOT=<path to your root folder>/ npm run hot-proxy` from the storefronts js directory.
    * The default port is still port 9999.
* Fixed to show the listing loader for `cms-element-product-listing`
    * Added `cmsProductListingWrapperSelector` property in `listing.plugin.js`
    * Added `addLoadingElementLoaderClass` function in `listing.plugin.js`
    * Added `removeLoadingElementLoaderClass` function in `listing.plugin.js`
* Added block `page_checkout_item_quantity_number` in `page/checkout/checkout-item.html.twig` to other place can inherit
    * Replace block `page_checkout_item_quantity` to `page_checkout_item_quantity_form` in `account/order/line-item.html.twig`
* Fixed wrong meta tag value `twitter:card` in `storefront/layout/meta.html.twig`
* Fixed `packUnit` and `packUnitPlural` not being properly accessed in `buy-widget-form.html.twig`
* Deprecated template component/listing/breadcrumb.html.twig. Breadcrumb will be handled by generic layout/breadcrumb.html.twig.
* Deprecated template component/product/breadcrumb.html.twig. Breadcrumb will be handled by generic layout/breadcrumb.html.twig.
* Deprecated block page_product_detail_breadcrumb in page/product-detail/index.html.twig. Breadcrumb will be handled by block base_breadcrumb in storefront/base.html.twig.
* Fixed switching to domains with upper case paths like `https://example.com/de-DE`

6.3.0.0
----------------

#### Administration
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
* Refactor component `sw-customer-card` added inputs for password and password confirm
    * Added block `sw_customer_card_password`
    * Added block `sw_customer_card_password_confirm`
* Refactor `sw-customer-detail`
    * Added method `checkPassword` and use of it when editing customer 
    * Added success notification message
* Refactor `sw-settings-user-detail`
    * Added `newPasswordConfirm`
    * Fixed issue when saving new admin password
    * Disabled `change` button if passwords do not match
* Added language switch to Scale Units list page to translate scale units
* Added tooltips to the toolbar of text editor
* Added isInlineEdit property to component `sw-text-editor-toolbar`
* Price input fields substitute commas with dots automatically in Add Product page.
* Added a link to the customer name in the order overview. With this it is now possible to open the customer directly from the overview.
* Added property `fileAccept` to 
    * `sw-media-upload-v2`
    * `sw-media-compact-upload-v2`
    * `sw-media-modal-v2`
    * `sw-media-index`
* Change default value of `accept` in `sw-media-index` to `*/*` to allow all types of files in media management 
* Added config option for disabling reviews in the storefront
* Removed the Vue event `inline-edit-assign` from `onClickCancelInlineEdit` method in `src/Administration/Resources/app/administration/src/app/component/data-grid/sw-data-grid/index.js`
    * In order to react to saving or cancelling the inline-edit of the `sw-data-grid`, use the `inline-edit-save` and `inline-edit-cancel` events.
    * Refactored sw-mail-template
        * Replaced store with repositories    
    * Refactored Webpack configuration files to one single file
        * Removed `sw-devmode-loader.js`
        * Removed `build.js`
        * Removed `check-versions.js`
        * Removed `dev-client.js`
        * Removed `dev-server.js`
        * Removed `utils.js`
        * Removed `webpack.base.conf.js`
        * Removed `webpack.dev.conf.js`
        * Removed `webpack.prod.conf.js`
        * Removed `webpack.test.conf.js`
* Added `block` and `description` property to `sw-radio-field`. Furthermore, each `option` can now also have a `description`
* Deprecated data fetching methods in `ApiService` classes, use the repository class for data fetching instead
    * Deprecated `getList` method, use `src/core/data-new/repository.data.js` `search()` function instead
    * Deprecated `getById` method, use `src/core/data-new/repository.data.js` `get()` function instead
    * Deprecated `updateById` method, use `src/core/data-new/repository.data.js` `save()` function instead
    * Deprecated `deleteAssociation` method, use `src/core/data-new/repository.data.js` `delete()` function instead
    * Deprecated `create` method, use `src/core/data-new/repository.data.js` `create()` function instead
    * Deprecated `delete` method, use `src/core/data-new/repository.data.js` `delete()` function instead
    * Deprecated `clone` method, use `src/core/data-new/repository.data.js` `clone()` function instead
    * Deprecated `versionize` method, use `src/core/data-new/repository.data.js` `createVersion()` function instead
    * Deprecated `mergeVersion` method, use `src/core/data-new/repository.data.js` `mergeVersion()` function instead
    * Deprecated `getList` method  of `src/core/service/api/custom-field.service.js`, use `src/core/data-new/repository.data.js` `search()` function instead
    * Deprecated `getList` method  of `src/core/service/api/custom-field-set.service.js`, use `src/core/data-new/repository.data.js` `search()` function instead
    * Deprecated `getListByCustomerId` method  of `src/core/service/api/customer-address.api.service.js`, use `src/core/data-new/repository.data.js` `search()` function instead
    * Deprecated `save` method  of `src/core/service/api/snippet.api.service.js`, use `src/core/data-new/repository.data.js` `save()` function instead
* Refactored the `template.factory` to fix issues of inheritance when extending or overriding components 
* Replace the module 'sw-settings-user' with 'sw-users-permissions'
* Added listing for roles in users and permissions module
* Added fields for roles and jobTitle in user detail page
* Added `acl` service for checking if the user have the correct rights
* Added `privileges` service for adding new privileges
* Added editing for roles in users and permissions module
    * additional permissions
* Added some additional permissions and implement them in the admin
    * Order: create discounts
    * Cache: clear cache
    * System: update core
    * System: maintain plugins
* Change growl error message for unfilled required fields for saving entities
    * Added snippet `global.notification.notificationSaveErrorMessageRequiredField`sInvalid
* Fixed required fields in products for cross-sellings
* Added privilege for admin menu items
* Added error page for routes without the correct privileges
* Added a permission grid for users and permissions
* Added rudimentary permissions for sales channel
* Added `sw-product-feature-set-form` component
    * Changed `sw-product-detail-base` to include the new component, which enables users to assign essential characteristics templates to products
* Added some children routes in route `sw.sales.channel.detail.base` in `sw-sales-channel` module to handle step navigation of Google programs modal 
* Added `sw-sales-channel-google-programs-modal` component to handle Google programs setup
    * Added `sw-sales-channel-google-introduction` to handle Google account authentication and connection
    * Added `sw-sales-channel-google-authentication` to show Google account profile and handle disconnect functionality
    * Added `sw-sales-channel-google-merchant` component to show existing merchant accounts list and handle assigning existing merchant account or creating new account
    * Added `sw-sales-channel-google-shipping-setting` component to handle shipping setting selection
* Added salesChannel state in `sw-sales-channel` module
* Added ACL permissions to product module
* Added ACL permissions to currency module in settings
* Added `customFieldSetCriteria` computed property to `sw-customer-detail-base`
* Added `createdComponent` method to `sw-customer-detail-base`
* Added computed `customFieldRepository` to `sw-custom-field-list`
* Added method `onPageChange` to `sw-custom-field-list`
* Added method `loadCustomFields` in `sw-custom-field-list/index.js` which is responsible to load, paginate and search the custom fields directly via the API.
* Add hook `created` and method `createdComponent` to `sw-custom-field-list/index.js`
* Deprecated computed `filteredCustomFields` in `sw-custom-field-list/index.js`. The search is now done via request against the API.
* `repository.data.js` now sends a `sw-currency-id` header when setting the option `currencyId`
* `sw-entity-grid` now emits a new event `paginate` in method `paginate`
* `sw-entity-listing` methods `sort` and `paginate` can now return `false` when using the boolean option `useCustomSort` on a grid column.
    * The further execution of `doSearch` will be intercepted and custom logic can be implemented instead.
* Added data prop `lastSortedColumn` to `sw-entity-listing`
* Method `getCurrencyPriceByCurrencyId` in `sw-product-list/index.js` now receives parameters `(currencyId, prices)` instead of `(itemId, currencyId)`
* Method `onColumnSort` added in `sw-product-list/index.js`
* Added method `loadCurrentSalesChannelConfig` to `sw-system-config` to loads the current sales channel config without using the cached value 
* `sw-entity-multi-id-select` can now consider criteria in result lists
* Removed unused snippet keys from `src/app/snippet/de-DE.json`:
    * `sw-file-input.invalidFileType.title`
    * `sw-file-input.invalidFileSize.title`
    * `sw-media-modal-folder-dissolve.titleModal`
    * `sw-media-upload.notification.success.title`
    * `sw-config-form-renderer.configLoadErrorTitle`
* Removed unused snippet keys from `src/app/snippet/en-GB.json`:
    * `sw-file-input.invalidFileType.title`
    * `sw-file-input.invalidFileSize.title`
    * `sw-media-modal-folder-dissolve.titleModal`
    * `sw-media-upload.notification.success.title`
    * `sw-config-form-renderer.configLoadErrorTitle`
* Removed unused snippet keys from `src/module/sw-category/snippet/de-DE.json`:
    * `sw-category.general.titleSaveSuccess`
    * `sw-category.modal.modalTitleDelete`    
* Removed unused snippet keys from `src/module/sw-category/snippet/en-GB.json`:
    * `sw-category.general.titleSaveSuccess`
    * `sw-category.modal.modalTitleDelete` 
* Removed unused snippet keys from `src/module/sw-cms/snippet/de-DE.json`:
    * `sw-cms.detail.notification.titleMissingBlockFields` 
    * `sw-cms.detail.notification.titleMissingProductListing`
    * `sw-cms.detail.notification.titlePageError` 
    * `sw-cms.detail.notification.titleMissingBlocks` 
    * `sw-cms.detail.notification.titleMissingElements`
    * `sw-cms.components.cmsListItem.notificationDeleteErrorTitle`       
    * `sw-cms.components.cmsListItem.modal.modalTitleDelete`
* Removed unused snippet keys from `src/module/sw-cms/snippet/en-GB.json`:
    * `sw-cms.detail.notification.titleMissingBlockFields` 
    * `sw-cms.detail.notification.titleMissingProductListing`
    * `sw-cms.detail.notification.titlePageError` 
    * `sw-cms.detail.notification.titleMissingBlocks` 
    * `sw-cms.detail.notification.titleMissingElements`
    * `sw-cms.components.cmsListItem.notificationDeleteErrorTitle`       
    * `sw-cms.components.cmsListItem.modal.modalTitleDelete`
* Removed unused snippet keys from `src/module/sw-customer/snippet/de-DE.json`:
    * `sw-customer.list.modalTitleDelete`
    * `sw-customer.baseForm.modalTitleDeleteAddress`
    * `sw-customer.detail.titleSaveError`
    * `sw-customer.detail.titleSaveSuccess`
* Removed unused snippet keys from `src/module/sw-customer/snippet/en-GB.json`:
    * `sw-customer.list.modalTitleDelete`
    * `sw-customer.baseForm.modalTitleDeleteAddress`
    * `sw-customer.detail.titleSaveError`
    * `sw-customer.detail.titleSaveSuccess`
* Removed unused snippet keys from `src/module/sw-first-run-wizard/snippet/de-DE.json`:
    * `sw-first-run-wizard.welcome.success`
    * `sw-first-run-wizard.welcome.error`
* Removed unused snippet keys from `src/module/sw-first-run-wizard/snippet/en-GB.json`:
    * `sw-first-run-wizard.welcome.success`
    * `sw-first-run-wizard.welcome.error`
* Removed unused snippet keys from `src/module/sw-import-export/snippet/de-DE.json`:
    * `sw-import-export.profile.titleSaveSuccess`
    * `sw-import-export.profile.titleSaveError`
    * `sw-import-export.profile.titleDeleteSuccess`
    * `sw-import-export.profile.titleDeleteError`
    * `sw-import-export.profile.titleDuplicateSuccess`
    * `sw-import-export.profile.titleDuplicateError`
    * `sw-import-export.profile.validationError`
    * `sw-import-export.exporter.titleExportSuccess`
    * `sw-import-export.exporter.errorNotificationTitle`
    * `sw-import-export.importer.titleExportSuccess`
    * `sw-import-export.importer.titleImportError`
    * `sw-import-export.importer.errorNotificationTitle`
* Removed unused snippet keys from `src/module/sw-import-export/snippet/en-GB.json`:
    * `sw-import-export.profile.titleSaveSuccess`
    * `sw-import-export.profile.titleSaveError`
    * `sw-import-export.profile.titleDeleteSuccess`
    * `sw-import-export.profile.titleDeleteError`
    * `sw-import-export.profile.titleDuplicateSuccess`
    * `sw-import-export.profile.titleDuplicateError`
    * `sw-import-export.profile.validationError`
    * `sw-import-export.exporter.titleExportSuccess`
    * `sw-import-export.exporter.errorNotificationTitle`
    * `sw-import-export.importer.titleExportSuccess`
    * `sw-import-export.importer.titleImportError`
    * `sw-import-export.importer.errorNotificationTitle`
* Removed unused snippet keys from `src/module/sw-integration/snippet/de-DE.json`:
    * `sw-integration.detail.titleSaveSuccess`
    * `sw-integration.detail.titleSaveError`
    * `sw-integration.detail.titleCreateNewError`
* Removed unused snippet keys from `src/module/sw-integration/snippet/en-GB.json`:
    * `sw-integration.detail.titleSaveSuccess`
    * `sw-integration.detail.titleSaveError`
    * `sw-integration.detail.titleCreateNewError`
* Removed unused snippet keys from `src/module/sw-login/snippet/de-DE.json`:
    * `sw-login.recovery.error.passwordUpdateTitle`
* Removed unused snippet keys from `src/module/sw-login/snippet/en-GB.json`:
    * `sw-login.recovery.error.passwordUpdateTitle`
* Removed unused snippet keys from `src/module/sw-mail-template/snippet/de-DE.json`:
    * `sw-mail-template.general.notificationTestMailErrorTitle`
    * `sw-mail-template.general.notificationTestMailSuccessTitle`
    * `sw-mail-template.list.modalTitleDelete`
    * `sw-mail-template.detail.titleSaveSuccess`
    * `sw-mail-template.detail.titleSaveError`
    * `sw-mail-header-footer.detail.titleSaveSuccess`
    * `sw-mail-header-footer.detail.titleSaveError`
* Removed unused snippet keys from `src/module/sw-mail-template/snippet/en-GB.json`:
    * `sw-mail-template.general.notificationTestMailErrorTitle`
    * `sw-mail-template.general.notificationTestMailSuccessTitle`
    * `sw-mail-template.list.modalTitleDelete`
    * `sw-mail-template.detail.titleSaveSuccess`
    * `sw-mail-template.detail.titleSaveError`
    * `sw-mail-header-footer.detail.titleSaveSuccess`
    * `sw-mail-header-footer.detail.titleSaveError`     
* Removed unused snippet keys from `src/module/sw-manufacturer-template/snippet/de-DE.json`:    
    * `sw-manufacturer.detail.titleSaveSuccess`   
* Removed unused snippet keys from `src/module/sw-manufacturer-template/snippet/en-GB.json`:    
    * `sw-manufacturer.detail.titleSaveSuccess`
* Removed unused snippet keys from `src/module/sw-media/snippet/de-DE.json`:
    * `sw-media.general.notification.title`     
* Removed unused snippet keys from `src/module/sw-media/snippet/en-GB.json`:
    * `sw-media.general.notification.title`  
* Removed unused snippet keys from `src/module/sw-newsletter-recipient/snippet/de-DE.json`:     
    * `sw-newsletter-recipient.detail.titleSaveSuccess`
    * `sw-newsletter-recipient.detail.titleSaveError`
* Removed unused snippet keys from `src/module/sw-newsletter-recipient/snippet/ne-GB.json`:
    * `sw-newsletter-recipient.detail.titleSaveSuccess`
    * `sw-newsletter-recipient.detail.titleSaveError`
* Removed unused snippet keys from `src/module/sw-order/snippet/de-DE.json`:   
    * `sw-order.list.modalTitleDelete`
    * `sw-order.list.titleRecalculationError`
    * `sw-order.detail.titleSaveError`
    * `sw-order.detail.notification.shippingNotAvailable.title`
    * `sw-order.create.titleSaveError`
    * `sw-order.create.titleFetchError`
    * `sw-order.stateCard.headlineErrorStateChange`
Refactored `src/module/sw-order/snippet/de-DE.json`:
    * `sw-order.detail.notification.shippingNotAvailable.message`
    to
    * `sw-order.detail.notification.messageShippingNotAvailable`
* Removed unused snippet keys from `src/module/sw-order/snippet/en-GB.json`:   
    * `sw-order.list.modalTitleDelete`
    * `sw-order.list.titleRecalculationError`
    * `sw-order.detail.titleSaveError`
    * `sw-order.detail.notification.shippingNotAvailable.title`
    * `sw-order.create.titleSaveError`
    * `sw-order.create.titleFetchError`
    * `sw-order.stateCard.headlineErrorStateChange`
Refactored `src/module/sw-order/snippet/en-GB.json`:
    * `sw-order.detail.notification.shippingNotAvailable.message`
    to
    * `sw-order.detail.notification.messageShippingNotAvailable`
* Removed unused snippet keys from `src/module/sw-plugin/snippet/de-DE.json`:
    * `sw-plugin.errors.titleGenericFailure`
    * `sw-plugin.errors.titleUploadFailure`
    * `sw-plugin.errors.titleLoginDataInvalid`
    * `sw-plugin.errors.titleStoreHostMissing`
    * `sw-plugin.errors.titleStoreNotAvailable`
    * `sw-plugin.errors.titlePluginInstallationFailed`
    * `sw-plugin.errors.titlePluginUninstallationFailed`
    * `sw-plugin.errors.titlePluginActivationFailed`
    * `sw-plugin.errors.titlePluginDeactivationFailed`
    * `sw-plugin.errors.titlePluginUpdateFailed`
    * `sw-plugin.fileUpload.titleUploadSuccess`
    * `sw-plugin.store-login.titleLoginSuccess`
    * `sw-plugin.list.titleActivateSuccess`
    * `sw-plugin.list.titleDeactivateSuccess`
    * `sw-plugin.list.titleUninstallSuccess`
    * `sw-plugin.list.titleDeleteSuccess`
    * `sw-plugin.list.titleUpdateSuccess`
    * `sw-plugin.list.titleDeleteConfirm`
    * `sw-plugin.updates.titleUpdateSuccess`
    * `sw-plugin.sw-plugin-config.titleSaveSuccess`
    * `sw-plugin.sw-plugin-config.titleSaveError`
Refactored `src/module/sw-plugin/snippet/de-DE.json`:    
    * `sw-plugin.store-login.titleLoginMessage`
    to
    * `sw-plugin.store-login.loginMessage` 
* Removed unused snippet keys from `src/module/sw-plugin/snippet/en-GB.json`:
    * `sw-plugin.errors.titleGenericFailure`
    * `sw-plugin.errors.titleUploadFailure`
    * `sw-plugin.errors.titleLoginDataInvalid`
    * `sw-plugin.errors.titleStoreHostMissing`
    * `sw-plugin.errors.titleStoreNotAvailable`
    * `sw-plugin.errors.titlePluginInstallationFailed`
    * `sw-plugin.errors.titlePluginUninstallationFailed`
    * `sw-plugin.errors.titlePluginActivationFailed`
    * `sw-plugin.errors.titlePluginDeactivationFailed`
    * `sw-plugin.errors.titlePluginUpdateFailed`
    * `sw-plugin.fileUpload.titleUploadSuccess`
    * `sw-plugin.store-login.titleLoginSuccess`
    * `sw-plugin.list.titleActivateSuccess`
    * `sw-plugin.list.titleDeactivateSuccess`
    * `sw-plugin.list.titleUninstallSuccess`
    * `sw-plugin.list.titleDeleteSuccess`
    * `sw-plugin.list.titleUpdateSuccess`
    * `sw-plugin.list.titleDeleteConfirm`
    * `sw-plugin.updates.titleUpdateSuccess`
    * `sw-plugin.sw-plugin-config.titleSaveSuccess`
    * `sw-plugin.sw-plugin-config.titleSaveError`
Refactored `src/module/sw-plugin/snippet/en-GB.json`:    
    * `sw-plugin.store-login.titleLoginMessage`
    to
    * `sw-plugin.store-login.loginMessage` 
* Removed unused snippet keys from `src/module/sw-product-stream/snippet/de-DE.json`:
    * `sw-product-stream.detail.titleSaveSuccess`
* Removed unused snippet keys from `src/module/sw-product-stream/snippet/en-GB.json`:
    * `sw-product-stream.detail.titleSaveSuccess`
* Removed unused snippet keys from `src/module/sw-product/snippet/de-DE.json`:
    * `sw-product.list.modalTitleDelete`
    * `sw-product.list.titleSaveSuccess`
    * `sw-product.detail.titleSaveSuccess`
    * `sw-product.detail.titleSaveWarning`
    * `sw-product.variations.generatedListDeleteModalTitle`
    * `sw-product.variations.generatedListTitleSaveSuccess`
    * `sw-product.variations.generatedListTitleSaveError`
* Removed unused snippet keys from `src/module/sw-product/snippet/en-GB.json`:
    * `sw-product.list.modalTitleDelete`
    * `sw-product.list.titleSaveSuccess`
    * `sw-product.detail.titleSaveSuccess`
    * `sw-product.detail.titleSaveWarning`
    * `sw-product.variations.generatedListDeleteModalTitle`
    * `sw-product.variations.generatedListTitleSaveSuccess`
    * `sw-product.variations.generatedListTitleSaveError`
* Removed unused snippet keys from `src/module/sw-promotion/snippet/de-DE.json`:
    * `sw-promotion.list.modalTitleDelete`
    * `sw-promotion.detail.TitleSaveSuccess`
* Removed unused snippet keys from `src/module/sw-promotion/snippet/en-GB.json`:
    * `sw-promotion.list.modalTitleDelete`
    * `sw-promotion.detail.TitleSaveSuccess`
* Removed unused snippet keys from `src/module/sw-property/snippet/de-DE.json`:    
    * `sw-property.list.modalTitleDelete`
    * `sw-property.detail.titleSaveSuccess`
    * `sw-property.detail.titleSaveError`      
* Removed unused snippet keys from `src/module/sw-property/snippet/en-GB.json`:    
    * `sw-property.list.modalTitleDelete`
    * `sw-property.detail.titleSaveSuccess`
    * `sw-property.detail.titleSaveError` 
* Removed unused snippet keys from `src/module/sw-review/snippet/de-DE.json`:   
    * `sw-review.detail.titleSaveSuccess`
* Removed unused snippet keys from `src/module/sw-review/snippet/en-GB.json`:   
    * `sw-review.detail.titleSaveSuccess`
* Removed unused snippet keys from `src/module/sw-sales-channel/snippet/de-DE.json`: 
    * `sw.sales.channel.detail.titleSaveSuccess`
    * `sw.sales.channel.detail.titleSaveError`
    * `sw.sales.channel.detail.titleActivateError`
    * `sw.sales.channel.detail.titleFetchError`
    * `sw.sales.channel.detail.titleDeleteSalesChannelWarning`  
    * `sw-sales-channel.detail.productComparison.titleAccessKeyChanged`  
* Removed unused snippet keys from `src/module/sw-sales-channel/snippet/en-GB.json`: 
    * `sw.sales.channel.detail.titleSaveSuccess`
    * `sw.sales.channel.detail.titleSaveError`
    * `sw.sales.channel.detail.titleActivateError`
    * `sw.sales.channel.detail.titleFetchError`
    * `sw.sales.channel.detail.titleDeleteSalesChannelWarning`  
    * `sw-sales-channel.detail.productComparison.titleAccessKeyChanged` 
* Removed unused snippet keys from `src/module/sw-settings-address/snippet/de-DE.json`:
    * `sw-settings-address.general.titleSaveSuccess`
    * `sw-settings-address.general.titleSaveError` 
* Removed unused snippet keys from `src/module/sw-settings-address/snippet/en-GB.json`:
    * `sw-settings-address.general.titleSaveSuccess`
    * `sw-settings-address.general.titleSaveError`
* Removed unused snippet keys from `src/module/sw-settings-basic-information/snippet/de-DE.json`:
    * `sw-settings-basic-information.general.titleSaveSuccess`
    * `sw-settings-basic-information.general.titleSaveError`
* Removed unused snippet keys from `src/module/sw-settings-basic-information/snippet/en-GB.json`:
    * `sw-settings-basic-information.general.titleSaveSuccess`
    * `sw-settings-basic-information.general.titleSaveError`  
* Removed unused snippet keys from `src/module/sw-settings-cart/snippet/de-DE.json`:
    * `sw-settings-cart.general.titleSaveSuccess`
    * `sw-settings-cart.general.titleSaveError`     
* Removed unused snippet keys from `src/module/sw-settings-cart/snippet/en-GB.json`:
    * `sw-settings-cart.general.titleSaveSuccess`
    * `sw-settings-cart.general.titleSaveError` 
* Removed unused snippet keys from `src/module/sw-settings-country/snippet/de-DE.json`:
    * `sw-settings-country.list.modalTitleDelete`
    * `sw-settings-country.list.titleDeleteSuccess`
    * `sw-settings-country.detail.titleSaveSuccess`
    * `sw-settings-country.detail.titleSaveError`
* Removed unused snippet keys from `src/module/sw-settings-country/snippet/en-GB.json`:
    * `sw-settings-country.list.modalTitleDelete`
    * `sw-settings-country.list.titleDeleteSuccess`
    * `sw-settings-country.detail.titleSaveSuccess`
    * `sw-settings-country.detail.titleSaveError`
* Removed unused snippet keys from `src/module/sw-settings-currency/snippet/de-DE.json`:
    * `sw-settings-currency.list.modalTitleDelete`
    * `sw-settings-currency.list.titleDeleteSuccess`
    * `sw-settings-currency.detail.titleSaveSuccess`
    * `sw-settings-currency.detail.notificationErrorTitle`
* Removed unused snippet keys from `src/module/sw-settings-currency/snippet/en-GB.json`:
    * `sw-settings-currency.list.modalTitleDelete`
    * `sw-settings-currency.list.titleDeleteSuccess`
    * `sw-settings-currency.detail.titleSaveSuccess`
    * `sw-settings-currency.detail.notificationErrorTitle`
* Removed unused snippet keys from `src/module/sw-settings-custom-field/snippet/de-DE.json`:
    * `sw-settings-custom-field.set.list.titleModalDelete`
    * `sw-settings-custom-field.set.list.titleDeleteSuccess`
    * `sw-settings-custom-field.set.detail.titleSaveSuccess`
    * `sw-settings-custom-field.set.detail.titleNameNotUnique`
* Removed unused snippet keys from `src/module/sw-settings-custom-field/snippet/en-GB.json`:
    * `sw-settings-custom-field.set.list.titleModalDelete`
    * `sw-settings-custom-field.set.list.titleDeleteSuccess`
    * `sw-settings-custom-field.set.detail.titleSaveSuccess`
    * `sw-settings-custom-field.set.detail.titleNameNotUnique`
* Removed unused snippet keys from `src/module/sw-settings-customer-group/snippet/de-DE.json`:
    * `sw-settings-customer-group.detail.notificationErrorTitle`
    * `sw-settings-customer-group.detail.notification.errorTitleCannotDeleteCustomerGroup`
* Removed unused snippet keys from `src/module/sw-settings-customer-group/snippet/en-GB.json`:
    * `sw-settings-customer-group.detail.notificationErrorTitle`
    * `sw-settings-customer-group.detail.notification.errorTitleCannotDeleteCustomerGroup`
* Removed unused snippet keys from `src/module/sw-settings-document/snippet/de-DE.json`:
    * `sw-settings-document.list.modalTitleDelete`
    * `sw-settings-document.list.titleDeleteSuccess`
    * `sw-settings-document.detail.titleSaveSuccess`
* Removed unused snippet keys from `src/module/sw-settings-document/snippet/en-GB.json`:
    * `sw-settings-document.list.modalTitleDelete`
    * `sw-settings-document.list.titleDeleteSuccess`
    * `sw-settings-document.detail.titleSaveSuccess`
* Removed unused snippet keys from `src/module/sw-settings-language/snippet/de-DE.json`:
    * `sw-settings-language.list.modalTitleDelete`
    * `sw-settings-language.list.titleDeleteSuccess`
    * `sw-settings-language.detail.titleSaveSuccess`
    * `sw-settings-language.detail.titleAlertChangeParent`
* Removed unused snippet keys from `src/module/sw-settings-language/snippet/en-GB.json`:
    * `sw-settings-language.list.modalTitleDelete`
    * `sw-settings-language.list.titleDeleteSuccess`
    * `sw-settings-language.detail.titleSaveSuccess`
    * `sw-settings-language.detail.titleAlertChangeParent`
* Removed unused snippet keys from `src/module/sw-settings-listing/snippet/de-DE.json`:
    * `sw-settings-listing.general.titleSaveSuccess`
    * `sw-settings-listing.general.titleSaveError`
* Removed unused snippet keys from `src/module/sw-settings-listing/snippet/en-GB.json`:
    * `sw-settings-listing.general.titleSaveSuccess`
    * `sw-settings-listing.general.titleSaveError`
* Removed unused snippet keys from `src/module/sw-settings-login-registration/snippet/de-DE.json`:
    * `sw-settings-login-registration.general.titleSaveSuccess`
    * `sw-settings-login-registration.general.titleSaveError`
* Removed unused snippet keys from `src/module/sw-settings-login-registration/snippet/en-GB.json`:
    * `sw-settings-login-registration.general.titleSaveSuccess`
    * `sw-settings-login-registration.general.titleSaveError`
* Removed unused snippet keys from `src/module/sw-settings-number-range/snippet/de-DE.json`:
    * `sw-settings-number-range.list.modalTitleDelete`
    * `sw-settings-number-range.list.titleDeleteSuccess`
    * `sw-settings-number-range.detail.titleSaveSuccess`
    * `sw-settings-number-range.detail.titleSaveError`
    * `sw-settings-number-range.detail.errorSalesChannelNeededTitle`
    * `sw-settings-number-range.detail.errorPatternNeededTitle`
* Removed unused snippet keys from `src/module/sw-settings-number-range/snippet/en-GB.json`:
    * `sw-settings-number-range.list.modalTitleDelete`
    * `sw-settings-number-range.list.titleDeleteSuccess`
    * `sw-settings-number-range.detail.titleSaveSuccess`
    * `sw-settings-number-range.detail.titleSaveError`
    * `sw-settings-number-range.detail.errorSalesChannelNeededTitle`
    * `sw-settings-number-range.detail.errorPatternNeededTitle`
* Removed unused snippet keys from `src/module/sw-settings-payment/snippet/de-DE.json`:
    * `sw-settings-payment.list.modalTitleDelete`
    * `sw-settings-payment.list.titleDeleteSuccess`
    * `sw-settings-payment.detail.titleSaveSuccess`
* Removed unused snippet keys from `src/module/sw-settings-payment/snippet/en-GB.json`:
    * `sw-settings-payment.list.modalTitleDelete`
    * `sw-settings-payment.list.titleDeleteSuccess`
    * `sw-settings-payment.detail.titleSaveSuccess`
* Removed unused snippet keys from `src/module/sw-settings-rule/snippet/de-DE.json`:
    * `sw-settings-rule.detail.titleSaveSuccess`
    * `sw-settings-rule.detail.titleSaveError`
    * `sw-settings-rule.conditionModal.titleSaveError`
* Removed unused snippet keys from `src/module/sw-settings-rule/snippet/e-GB.json`:
    * `sw-settings-rule.detail.titleSaveSuccess`
    * `sw-settings-rule.detail.titleSaveError`
    * `sw-settings-rule.conditionModal.titleSaveError`
* Removed unused snippet keys from `src/module/sw-settings-salutation/snippet/de-DE.json`:
    * `sw-settings-salutation.detail.notificationErrorTitle`  
* Removed unused snippet keys from `src/module/sw-settings-salutation/snippet/de-DE.json`:
    * `sw-settings-salutation.detail.notificationErrorTitle`  
 * Removed unused snippet keys from `src/module/sw-settings-seo/snippet/de-DE.json`:
    * `sw-seo-url-template-card.general.titleSaveError` 
    * `sw-seo-url-template-card.general.titleSaveSuccess`
* Removed unused snippet keys from `src/module/sw-settings-seo/snippet/de-DE.json`:
    * `sw-seo-url-template-card.general.titleSaveError` 
    * `sw-seo-url-template-card.general.titleSaveSuccess`
* Removed unused snippet keys from `src/module/sw-settings-shipping/snippet/de-DE.json`:
    * `sw-settings-shipping.list.modalTitleDelete`
    * `sw-settings-shipping.list.titleSaveSuccess`
    * `sw-settings-shipping.list.titleDeleteSuccess`
    * `sw-settings-shipping.detail.titleSaveSuccess`
    * `sw-settings-shipping.priceMatrix.modalTitleDelete`
    * `sw-settings-shipping.priceMatrix.deletionNotPossibleTitle`
    * `sw-settings-shipping.priceMatrix.unrestrictedRuleAlreadyExistsTitle`
    * `sw-settings-shipping.priceMatrix.newMatrixAlertTitle`     
* Removed unused snippet keys from `src/module/sw-settings-shipping/snippet/en-GB.json`:
    * `sw-settings-shipping.list.modalTitleDelete`
    * `sw-settings-shipping.list.titleSaveSuccess`
    * `sw-settings-shipping.list.titleDeleteSuccess`
    * `sw-settings-shipping.detail.titleSaveSuccess`
    * `sw-settings-shipping.priceMatrix.modalTitleDelete`
    * `sw-settings-shipping.priceMatrix.deletionNotPossibleTitle`
    * `sw-settings-shipping.priceMatrix.unrestrictedRuleAlreadyExistsTitle`
    * `sw-settings-shipping.priceMatrix.newMatrixAlertTitle`
* Removed unused snippet keys from `src/module/sw-settings-shopware-updates/snippet/de-DE.json`:
    * `sw-settings-shopware-updates.notifications.title`           
* Removed unused snippet keys from `src/module/sw-settings-shopware-updates/snippet/en-GB.json`:
    * `sw-settings-shopware-updates.notifications.title` 
* Removed unused snippet keys from `src/module/sw-settings-sitemap/snippet/de-DE.json`:      
    * `sw-settings-sitemap.general.titelSaveSuccess`
    * `sw-settings-sitemap.general.titelSaveError`
* Removed unused snippet keys from `src/module/sw-settings-sitemap/snippet/de-DE.json`:      
    * `sw-settings-sitemap.general.titelSaveSuccess`
    * `sw-settings-sitemap.general.titelSaveError`   
* Removed unused snippet keys from `src/module/sw-settings-snippet/snippet/de-DE.json`:
    * `sw-settings-snippet.general.errorBackRoutingTitle`
    * `sw-settings-snippet.detail.titleSaveError`
    * `sw-settings-snippet.detail.titleSaveSuccess`
    * `sw-settings-snippet.list.modalTitleDelete`
    * `sw-settings-snippet.list.titleDeleteSuccess`
    * `sw-settings-snippet.list.titleSaveError`
    * `sw-settings-snippet.list.titleSaveSuccess`
    * `sw-settings-snippet.setList.cloneNoteErrorTitle`
    * `sw-settings-snippet.setList.cloneNoteSuccessTitle`
    * `sw-settings-snippet.setList.deleteNoteErrorTitle`
    * `sw-settings-snippet.setList.deleteNoteSuccessTitle`
    * `sw-settings-snippet.setList.inlineEditErrorTitle`
    * `sw-settings-snippet.setList.inlineEditSuccessTitle`
    * `sw-settings-snippet.setList.modalTitleDelete`
    * `sw-settings-snippet.setList.notEditableNoteErrorTitle`
* Removed unused snippet keys from `src/module/sw-settings-snippet/snippet/en-GB.json`:
    * `sw-settings-snippet.general.errorBackRoutingTitle`
    * `sw-settings-snippet.detail.titleSaveError`
    * `sw-settings-snippet.detail.titleSaveSuccess`
    * `sw-settings-snippet.list.modalTitleDelete`
    * `sw-settings-snippet.list.titleDeleteSuccess`
    * `sw-settings-snippet.list.titleSaveError`
    * `sw-settings-snippet.list.titleSaveSuccess`
    * `sw-settings-snippet.setList.cloneNoteErrorTitle`
    * `sw-settings-snippet.setList.cloneNoteSuccessTitle`
    * `sw-settings-snippet.setList.deleteNoteErrorTitle`
    * `sw-settings-snippet.setList.deleteNoteSuccessTitle`
    * `sw-settings-snippet.setList.inlineEditErrorTitle`
    * `sw-settings-snippet.setList.inlineEditSuccessTitle`
    * `sw-settings-snippet.setList.modalTitleDelete`
    * `sw-settings-snippet.setList.notEditableNoteErrorTitle`
* Removed unused snippet keys from `src/module/sw-settings-store/snippet/de-DE.json`:
    * `sw-settings-store.general.titleSaveSuccess`
    * `sw-settings-store.general.titleSaveError`
* Removed unused snippet keys from `src/module/sw-settings-store/snippet/en-GB.json`:
    * `sw-settings-store.general.titleSaveSuccess`
    * `sw-settings-store.general.titleSaveError`
* Removed unused snippet keys from `src/module/sw-settings-tax/snippet/de-DE.json`:
    * `sw-settings-tax.list.modalTitleDelete`
    * `sw-settings-tax.list.titleDeleteSuccess`
    * `sw-settings-tax.detail.notificationErrorTitle`
* Removed unused snippet keys from `src/module/sw-settings-tax/snippet/en-GB.json`:
    * `sw-settings-tax.list.modalTitleDelete`
    * `sw-settings-tax.list.titleDeleteSuccess`
    * `sw-settings-tax.detail.notificationErrorTitle`
* Removed unused snippet keys from `src/module/sw-settings-units/snippet/de-DE.json`:
    * `sw-settings-units.notification.successTitle`
    * `sw-settings-units.notification.errorTitle`
* Removed unused snippet keys from `src/module/sw-settings-units/snippet/en-GB.json`:
    * `sw-settings-units.notification.successTitle`
    * `sw-settings-units.notification.errorTitle`
* Removed unused snippet keys from `src/module/sw-settings-user/snippet/de-DE.json`:
    * `sw-users-permissions.users.user-detail.modal.deleteModalTitle`
    * `sw-users-permissions.user-detail.modal.detailModalTitleEdit`
    * `sw-users-permissions.user-detail.modal.titleSaveSuccess`
    * `sw-users-permissions.user-detail.modal.titleSaveError`
    * `sw-users-permissions.user-detail.modal.titleCreateNewError`
* Removed unused snippet keys from `src/module/sw-settings-user/snippet/en-GB.json`:
    * `sw-users-permissions.users.user-detail.modal.deleteModalTitle`
    * `sw-users-permissions.user-detail.modal.detailModalTitleEdit`
    * `sw-users-permissions.user-detail.modal.titleSaveSuccess`
    * `sw-users-permissions.user-detail.modal.titleSaveError`
    * `sw-users-permissions.user-detail.modal.titleCreateNewError`
* Removed unused snippet keys from `src/module/sw-users-permissions/snippet/de-DE.json`:
    * `sw-users-permissions.users.role-grid.notification.deleteSuccess.title`
    * `sw-users-permissions.users.role-grid.notification.deleteError.title`
    * `sw-users-permissions.users.user-grid.notification.deleteSuccess.title`
    * `sw-users-permissions.users.user-grid.notification.deleteError.title`
    * `sw-users-permissions.users.user-grid.notification.deleteUserLoggedInError.title`
    * `sw-users-permissions.users.user-detail.modal.titleSaveSuccess`
    * `sw-users-permissions.users.user-detail.modal.titleSaveError`
    * `sw-users-permissions.users.user-grid.titleModalDelete`
    * `sw-users-permissions.users.user-detail.notification.saveError.title`
    * `sw-users-permissions.users.user-detail.notification.saveSuccess.title`
    * `sw-users-permissions.users.user-detail.modal.deleteModalTitle`
    * `sw-users-permissions.users.user-detail.modal.titleSaveSuccess`
    * `sw-users-permissions.users.user-detail.modal.titleSaveError`
    * `sw-users-permissions.users.user-detail.modal.titleCreateNewError`
    * `sw-users-permissions.users.user-detail.notification.saveError.title`
*Refactored `src/module/sw-users-permissions/snippet/de-DE.json`:
    * `sw-users-permissions.users.user-detail.notification.notificationInvalidEmailErrorMessage`
    to
    * `sw-users-permissions.users.user-detail.notification.invalidEmailErrorMessage`
* Removed unused snippet keys from `src/module/sw-users-permissions/snippet/en-GB.json`:
    * `sw-users-permissions.users.role-grid.notification.deleteSuccess.title`
    * `sw-users-permissions.users.role-grid.notification.deleteError.title`
    * `sw-users-permissions.users.user-grid.notification.deleteSuccess.title`
    * `sw-users-permissions.users.user-grid.notification.deleteError.title`
    * `sw-users-permissions.users.user-grid.notification.deleteUserLoggedInError.title`
    * `sw-users-permissions.users.user-detail.modal.titleSaveSuccess`
    * `sw-users-permissions.users.user-detail.modal.titleSaveError`
    * `sw-users-permissions.users.user-grid.titleModalDelete`
    * `sw-users-permissions.users.user-detail.notification.saveError.title`
    * `sw-users-permissions.users.user-detail.notification.saveSuccess.title`
    * `sw-users-permissions.users.user-detail.modal.deleteModalTitle`
    * `sw-users-permissions.users.user-detail.modal.titleSaveSuccess`
    * `sw-users-permissions.users.user-detail.modal.titleSaveError`
    * `sw-users-permissions.users.user-detail.modal.titleCreateNewError`
    * `sw-users-permissions.users.user-detail.notification.saveError.title`
*Refactored `src/module/sw-users-permissions/snippet/de-DE.json`:
    * `sw-users-permissions.users.user-detail.notification.notificationInvalidEmailErrorMessage`
    to
    * `sw-users-permissions.users.user-detail.notification.invalidEmailErrorMessage`
* Removed `login.service::getLocalStorageKey()`
* Added property `disabled` to component `sw-property-assignment`
* Added property `disabled` to component `sw-property-search`
* Added property `parentEntity` to component `sw-custom-field-set-renderer`
* Added property `showCustomFieldSetSelection` to component `sw-custom-field-set-renderer`
* Added computed variable `visibleCustomFieldSets` to component `sw-custom-field-set-renderer`
* Added property `helpText` to component `sw-inherit-wrapper`
* Added block `sw_inherit_wrapper_toggle_wrapper_help_text` to component `sw-inherit-wrapper`
* Added block `sw_product_detail_prices_price_empty_state_text_child` to component `sw-product-detail-context-prices`
* Added block `sw_product_detail_prices_price_empty_state_text_inherited` to component `sw-product-detail-context-prices`
* Added block `sw_product_detail_prices_price_empty_state_text_link` to component `sw-product-detail-context-prices`
* Added block `sw_product_detail_prices_price_empty_state_text_not_inherited` to component `sw-product-detail-context-prices`
* Added block `sw_product_detail_prices_price_empty_state_text_empty` to component `sw-product-detail-context-prices`
* Added block `sw_product_detail_prices_price_empty_state__inherit_switch` to component `sw-product-detail-context-prices`
* Added block `sw_product_detail_properties_assignment_card` to component `sw-product-detail-properties`
* Added block `sw_product_detail_properties_assignment_card_assignment` to component `sw-product-detail-properties`
* Added block `sw_product_detail_properties_assignment_card_empty` to component `sw-product-detail-properties`
* Added block `sw_product_detail_properties_empty_card` to component `sw-product-detail-properties`
* Added block `sw_product_detail_properties_empty_state` to component `sw-product-detail-properties`
* Added block `sw_product_detail_properties_empty_state_image` to component `sw-product-detail-properties`
* Added block `sw_product_detail_properties_empty_state_text` to component `sw-product-detail-properties`
* Added block `sw_product_detail_properties_empty_state_text_child` to component `sw-product-detail-properties`
* Added block `sw_product_detail_properties_empty_state_text_inherited` to component `sw-product-detail-properties`
* Added block `sw_product_detail_properties_empty_state_text_link` to component `sw-product-detail-properties`
* Added block `sw_product_detail_properties_empty_state_text_not_inherited` to component `sw-product-detail-properties`
* Added block `sw_product_detail_properties_empty_state_text_empty` to component `sw-product-detail-properties`
* Added block `sw_product_detail_properties_empty_state__inherit_switch` to component `sw-product-detail-properties`
* Added block `sw_product_detail_properties_assginment` to component `sw-product-detail-properties`
* Added block `sw_product_detail_properties_empty` to component `sw-product-detail-properties`
* Changed Vue `asset` filter to remove double slashes
* Fixed active state in the flyout navigation
* Fixed `sw-modal` styles for `variant="full"` to stay at full page size
* Custom fields assigned to a category entity can now also be configured in categories of type "link"
* Added block `sw_customer_list_sidebar_filter_items` to `sw-customer-list` allow easier adding filters to the sidebar
* Added block `sw_corder_list_sidebar_filter_items` to `sw-order-list` allow easier adding filters to the sidebar
* Fixed the sidebar with filters that wasn't displayed in the orderlist
* Fix truncated text in `sw-property-search`. Changed prop `flex` of `sw-grid-column` to `minmax(0, 1fr)`
* Fixed a bug where template resolving stopped the application from running if a `Component.override` or `Component.extend` was executed for a base component which was not registered. The application resolves this components to `false` and a `unknown custom element` exception will now be raised only if used in a template.

#### Core
* Deprecated `\Shopware\Core\Checkout\Cart\Tax\TaxRuleCalculator`, use `\Shopware\Core\Checkout\Cart\Tax\TaxCalculator` instead
* Added `Criteria $criteria` parameter in store api routes. The parameter will be required in 6.4. At the moment the parameter is commented out in the `*AbstractRoute`, but the following parameters are already passed:
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
* Removed `v-fixed` directive in `sw-entity-single-select` of `sw-order-product-select`  
* Refactor the component `sw_customer_base_form`
    * Removed snippet `sw-customer.baseForm.helpTextPassword`  
* Added new `\Shopware\Core\Checkout\Cart\SalesChannel\CartLoadRoute` class to allow fetching the cart using the store-api with the url GET `/store-api/v3/checkout/cart`
* Added new `\Shopware\Core\Checkout\Cart\SalesChannel\CartDeleteRoute` class to allow deleting the cart using the store-api with the url DELETE `/store-api/v3/checkout/cart`
* Added new `\Shopware\Core\Checkout\Cart\SalesChannel\CartItemAddRoute` class to allow adding line items to the cart using the store-api with the url POST `/store-api/v3/checkout/cart/line-item`
* Added new `\Shopware\Core\Checkout\Cart\SalesChannel\CartItemUpdateRoute` class to allow updating line items in the cart using the store-api with the url POST `/store-api/v3/checkout/cart/line-item`
* Added new `\Shopware\Core\Checkout\Cart\SalesChannel\CartItemRemoveRoute` class to allow deleting line items in the cart using the store-api with the url DELETE `/store-api/v3/checkout/cart/line-item`
* Added new `\Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRoute` class to allow placing an order in the store-api with the url POST `/store-api/v3/checkout/order`
* Added new `\Shopware\Core\System\Country\SalesChannel\CountryRoute` class to fetch available countries using the store-api with the url GET `/store-api/v3/country`
* Added new `\Shopware\Core\Checkout\Cart\LineItemFactoryRegistry` class to create and update line items from array input. It's limited to the available handlers. When you add a new line item type, you should consider creating a new handler. Following handlers are available in default:
    * `\Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory` - Creates product items
    * `\Shopware\Core\Checkout\Cart\LineItemFactoryHandler\PromotionLineItemFactory` - Creates promotion items from code
    * `\Shopware\Core\Checkout\Cart\LineItemFactoryHandler\CreditLineItemFactory` - Creates credit items, only allowed using with permissions
    * `\Shopware\Core\Checkout\Cart\LineItemFactoryHandler\CustomLineItemFactory` - Creates custom line items, only allowed using with permissions
    * To support your custom line item. Please create a new class which implements the `\Shopware\Core\Checkout\Cart\LineItemFactoryHandler\LineItemFactoryInterface` interface and is registered with the tag `shopware.cart.line_item.factory` in the di.
* Added new method `hasPermission` to `\Shopware\Core\System\SalesChannel\SalesChannelContext` to check permissions in the context
* Added new method `getOrders` to `\Shopware\Core\Checkout\Order\SalesChannel\OrderRouteResponse`
* Deprecated return object from method `getObject` in class `\Shopware\Core\Checkout\Order\SalesChannel\OrderRouteResponse`. It will return in future a `\Shopware\Core\Framework\Struct\ArrayStruct` instead of `OrderRouteResponseStruct`
* Added new constructor argument `$apiAlias` to `\Shopware\Core\Framework\Struct\ArrayStruct`. The given value will be used for `getApiAlias` method.
* Added new method `\Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister::delete`
* Deprecated `\Shopware\Core\System\Currency\CurrencyFormatter::formatCurrency`, use `\Shopware\Core\System\Currency\CurrencyFormatter::formatCurrencyByLanguage` instead
* Added `CloneBehavior $behavior` parameter to `\Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface::clone`. This parameter will be introduced in 6.4.0
* Added new entities needed for the essential characteristics feature
    * `\Shopware\Core\Content\Product\Aggregate\ProductFeature\ProductFeatureDefinition`
    * `\Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition`
    * `\Shopware\Core\Content\Product\Aggregate\ProductFeatureSetTranslation\ProductFeatureSetTranslationDefinition`
* Added `featureSets` association to `\Shopware\Core\Content\Product\ProductEntity`
* Added new class `Shopware\Core\System\Snippet\SnippetValidator` and interface `Shopware\Core\System\Snippet\SnippetValidatorInterface`
* Added new command `snippets:validate` with file `Shopware\Core\System\Snippet\Command\ValidateSnippetsCommand`
* Removed `Shopware\Core\Checkout\Cart\CartBehavior::$isRecalculation`
* Removed `Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterface`
* Removed `Shopware\Core\Checkout\Payment\Cart\Token\JWTFactory`
* Removed `Shopware\Core\Framework\Validation\ValidationServiceInterface`
* Removed `Shopware\Core\Checkout\Customer\Validation\AddressValidationFactory::buildCreateValidation`
* Removed `Shopware\Core\Checkout\Customer\Validation\AddressValidationFactory::buildUpdateValidation`
* Removed `Shopware\Core\Checkout\Customer\Validation\CustomerProfileValidationFactory::buildCreateValidation`
* Removed `Shopware\Core\Checkout\Customer\Validation\CustomerProfileValidationFactory::buildUpdateValidation`
* Removed `Shopware\Core\Checkout\Customer\Validation\CustomerValidationFactory::buildCreateValidation`
* Removed `Shopware\Core\Checkout\Customer\Validation\CustomerValidationFactory::buildUpdateValidation`
* Removed `Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler::pay`
* Removed `Shopware\Core\Checkout\Order\Validation\OrderValidationFactory::buildCreateValidation`
* Removed `Shopware\Core\Checkout\Order\Validation\OrderValidationFactory::buildUpdateValidation`
* Removed `Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackagerInterface`
* Removed `Shopware\Core\Checkout\Promotion\DataAbstractionLayer\Indexing\PromotionExclusionIndexer`
* Removed `Shopware\Core\Checkout\Promotion\DataAbstractionLayer\Indexing\PromotionRedemptionIndexer`
* Removed `Shopware\Core\Content\Category\DataAbstractionLayer\Indexing\BreadcrumbIndexer`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition::isChildCountAware`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition::isTreeAware`
* Removed `Shopware\Core\Content\ContactForm\Validation\ContactFormValidationFactory::buildCreateValidation`
* Removed `Shopware\Core\Content\ContactForm\Validation\ContactFormValidationFactory::buildUpdateValidation`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface`
* Removed `Shopware\Core\Content\ImportExport\DataAbstractionLayer\ImportExportLogValidator`
* Removed `Shopware\Core\Content\ProductStream\DataAbstractionLayer\Indexing\ProductStreamIndexer`
* Removed `Shopware\Core\Content\Rule\DataAbstractionLayer\Indexing\RulePayloadIndexer`
* `mail_template_media.language_id` is now required
* Removed `Shopware\Core\Content\MailTemplate\Service\MailerFactory   `
* `Shopware\Core\Content\MailTemplate\Service\MailService::buildContents` is now private
* Removed `Shopware\Core\Content\Media\DataAbstractionLayer\Indexing\MediaFolderConfigIndexer`
* Removed `Shopware\Core\Content\Media\DataAbstractionLayer\Indexing\MediaFolderSizeIndexer`
* Removed `Shopware\Core\Content\Media\DataAbstractionLayer\Indexing\MediaThumbnailIndexer`
* Removed `/sales-channel-api/v{version}/newsletter` route
* Removed `Shopware\Core\Content\Newsletter\NewsletterSubscriptionService`
* Removed `Shopware\Core\Content\Newsletter\NewsletterSubscriptionServiceInterface`
* Removed `Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductCategoryTreeIndexer`
* Removed `Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductListingPriceIndexer`
* Removed `Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductRatingAverageIndexer`
* Removed `Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductStockIndexer`
* Removed `Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\VariantListingIndexer`
* Removed `sort` query parameter support in storefront listings
* Removed `Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingGateway`
* Removed `Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingGatewayInterface`
* Removed `Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchGateway`
* Removed `Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchGatewayInterface`
* Removed `Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestGateway`
* Removed `Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestGatewayInterface`
* Removed `Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordIndexer`
* Removed `Shopware\Core\Content\Seo\DataAbstractionLayer\Indexing\SeoUrlIndexer`
* Removed `Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlExtractIdResult`
* Removed `Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig::$supportsNewIndexer`
* Removed `Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface::extractIdsToUpdate`
* Removed `Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface::getSeoVariables`
* Removed `Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateLoader`
* Removed `Shopware\Core\Content\Seo\Validation\SeoUrlValidationService`
* Removed `Shopware\Core\Content\Seo\SeoUrlGenerator::generateSeoUrls`
* Removed `Shopware\Core\Content\Seo\SeoUrlGenerator::checkUpdateAffectsTemplate`
* Removed `Shopware\Core\Content\Sitemap\Service\SitemapNameGenerator`
* Removed `Shopware\Core\Content\Sitemap\Service\SitemapNameGeneratorInterface`
* Removed `Shopware\Core\Content\Sitemap\Service\SitemapWriter`
* Removed `Shopware\Core\Content\Sitemap\Service\SitemapWriterInterface`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextWithHtmlField`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\LongTextWithHtmlFieldSerializer`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\Indexer\ChildCountIndexer`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\Indexer\InheritanceIndexer`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\Indexer\ManyToManyIdFieldIndexer`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\Indexer\TreeIndexer`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IndexerHandler`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IndexerMessage`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IndexerMessageSender`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistry`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistryEndEvent`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistryInterface`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistryPartialResult`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistryStartEvent`
* Removed `source` parameter in api requests
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::$source`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface`
* `Shopware\Core\Framework\Plugin\PluginManagementService::uploadPlugin` context parameter is now required
* Removed `Shopware\Core\Framework\Routing\RouteScopeInterface`
* Removed `Shopware\Core\Framework\Adapter\Twig\TemplateFinder::registerBundles`
* Removed `Shopware\Core\Framework\Adapter\Twig\TemplateFinderInterface::registerBundles`
* Removed `Shopware\Core\Framework\Validation\ValidationServiceInterface`
* Removed `Shopware\Core\Framework\Plugin`
* Removed `Shopware\Elasticsearch\Framework\Indexing\EntityIndexer`
* Removed `Shopware\Elasticsearch\Framework\Indexing\IndexingMessage`
* Removed `Shopware\Elasticsearch\Framework\Indexing\IndexingMessageHandler`
* Removed `Shopware\Elasticsearch\Framework\Indexing\IndexMessageDispatcher`
* Removed `Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition::hasNewIndexerPattern`
* Added new class `Shopware\Core\System\Snippet\SnippetValidator` and interface `Shopware\Core\System\Snippet\SnippetValidatorInterface`
* Added new command `snippets:validate` with file `Shopware\Core\System\Snippet\Command\ValidateSnippetsCommand`
* Added `Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete::$cloneRelevant` to skip association in clone process.
* Added new class `Shopware\Core\System\Snippet\SnippetValidator` and interface `Shopware\Core\System\Snippet\SnippetValidatorInterface`
* Added new command `snippets:validate` with file `Shopware\Core\System\Snippet\Command\ValidateSnippetsCommand`
* Added `\Shopware\Core\Content\Product\Cart\ProductFeatureBuilder` which is used to add features to the line item payload in `\Shopware\Core\Content\Product\Cart\ProductCartProcessor`
* Added new constructor argument `$featureBuilder` to `\Shopware\Core\Content\Product\Cart\ProductCartProcessor`
* Added new constants in `\Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition`:
    * `TYPE_PRODUCT_ATTRIBUTE`
    * `TYPE_PRODUCT_PROPERTY`
    * `TYPE_PRODUCT_CUSTOM_FIELD`
    * `TYPE_PRODUCT_REFERENCE_PRICE`
* Added new associations to the criteria in `\Shopware\Core\Content\Product\Cart\ProductGateway`:
    * `featureSets`
    * `properties.group`
* Added new `asset`, `sitemap` and  `theme` asset package
* Added new class `\Shopware\Core\Framework\Adapter\Asset\FlysystemLastModifiedVersionStrategy` which adds cache busting to asset urls with usage of flysystem adapters
* Added new class `\Shopware\Core\Framework\Adapter\Asset\FallbackUrlPackage` which adds a fallback to shop URL if the given URL is empty
* Added new class `\Shopware\Core\Framework\DependencyInjection\CompilerPass\FilesystemConfigMigrationCompilerPass` which fixes backward compatibility in the filesystem configs
* Changed `\Shopware\Core\Content\Seo\SalesChannel\StoreApiSeoResolver` to consider sales channel entities.
* Changed `/store-api/v1/handle-payment` to return the redirectUrl in json response.
* Added `\Shopware\Core\Framework\Routing\Annotation\ContextTokenRequired` to require a context token for sales-channel-api and store-api requests. 
    * Until v6.4.0 this will log a critical log message. From v6.4.0 on this will throw an exception.
    * To get a valid context token if you don't have one, call `/sales-channel-api/v{version}/context` first.
* Added new commands `system:config:set` and `system:config:get` to retrieve and set system config values
* Fixed `DeliveryCalculator` to only set shipping costs to zero if all items in cart have free shipping set
* Changed `\Shopware\Storefront\Theme\ThemeCompiler::dumpVariables` to enclose textarea variables in string delimiters
* Changed `\Shopware\Core\Checkout\Customer\SalesChannel\ResetPasswordRoute` to remove legacy password.
* Changed `\Shopware\Core\Content\Category\SalesChannel\NavigationRoute` to allow sending `buildTree` and `depth` as POST parameter
* Added ManyToManyIdField `tagIds` to `CustomerDefinition.php`
* Added new `Shopware\Core\Checkout\Cusomer\Rule\CustomerTagRule` to check for tags assigned to customer
* Events will now correctly stop event flow / propagation when `$event->stopPropagation()` is called
* Added composer dependency `psr/event-dispatcher`
* Added various primary keys, where it was missing
    * Added primary keys to initial Migrations
        * `Shopware/Core/Migration/Migration1536233510DocumentConfiguration`
        * `Shopware/Core/Migration/Migration1536233380UserRecovery`
        * `Shopware/Core/Migration/Migration1558505525Logging`
        * `Shopware/Core/Migration/Migration1570622696CustomerPasswordRecovery`
        * `Shopware/Core/Migration/Migration1570187167AddedAppConfig`
        * `Shopware/Core/Migration/Migration1587039363AddImportExportLabelField`
    * Added checks to set primary key, if not set yet
        * `Shopware/Core/Migration/Migration1572264837AddCacheId`
        * `Shopware/Core/Migration/Migration1594885630AddUserRecoveryPK`
        * `Shopware/Core/Migration/Migration1594886106AddDocumentBaseConfigSalesChannelPK`
        * `Shopware/Core/Migration/Migration1594886773LogEntryPK`
        * `Shopware/Core/Migration/Migration1594886895CustomerRecoveryPK`
        * `Shopware/Core/Migration/Migration1594887027AppConfigPK`
* Added new property `merged` and method `isMerged` to `LineItemAddedEvent`
* Added flag `ReadProtected` to `price`, `price` and `listingPrices` in `ProductDefinition`
* Added `HEADER_CURRENCY_ID` to `Shopware\Core\PlatformRequest`
* `Shopware\Core\Framework\Routing\ApiRequestContextResolver` is now able to resolve the `sw-currency-id` header
* Allow specifying translations for languages that don't exist. These translations will now be silently skipped.
  This used to throw the exception `\Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException`.
* Added check to prevent mails being sent when `\Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent::stopPropagation` was called before
* Increase the API version to v3
    * API version v1 is removed
    * API version v2 will be removed with next major version
* Added `ProductCartProcessor::ALLOW_PRODUCT_LABEL_OVERWRITES`
* Added `user:change-password` command to set the password of an administration user
* Added `HttpCacheGenerateKeyEvent` to allow changing the hash
* It is now possible to override the generic API routes with a `config/routes_overwrite.xml` in the bundle or plugin

#### Storefront
* Added plugin injection in hot mode
* Deprecated `window.accessKey` and `window.contextToken`, the variables contains now an empty string
* Removed `HttpClient()` constructor parameters in `src/Storefront/Resources/app/storefront/src/service/http-client.service.js`
* Fix timezone of `orderDate` in the ordergrid
* Added an image lazy loading capability to the `ZoomModalPlugin` which allows image loading only if the zoom modal is opened
* Refactored Webpack configuration files to one single file
    * Removed build/utils.js
    * Removed build/webpack.base.config.js
    * Removed build/webpack.dev.config.js
    * Removed build/webpack.hot.config.js
    * Removed build/webpack.prod.config.js
* Removed `/widgets/search/{search}` route
* Removed `Shopware\Storefront\Page\Search\SearchPage::$searchResult`
* Removed `Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration::getThemeVariableFile`
* Removed `Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration::setThemeVariableFile`
* Removed `Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration::createFromBundle`
* Removed `Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration::createFromConfigFile`
* Removed `Shopware\Storefront\Theme\Twig\ThemeTemplateFinder`
* Added some children routes in route `sw.sales.channel.detail.base` in `sw-sales-channel` module to handle step navigation of Google programs modal 
* Added `sw-sales-channel-google-programs-modal` component to handle Google programs setup
    * Added `sw-sales-channel-google-introduction` to handle Google account authentication and connection
    * Added `sw-sales-channel-google-authentication` to show Google account profile and handle disconnect functionality
    * Added `sw-sales-channel-google-merchant` component to show existing merchant accounts list and handle assigning existing merchant account or creating new account
    * Added `sw-sales-channel-google-shipping-setting` component to handle shipping setting selection
* Added salesChannel state in `sw-sales-channel` module
* Removed `input` event in `sw-multi-select`
* Removed `input` event in `sw-single-select`
* Removed `input` event in `sw-entity-many-to-many-select`
* Removed `input` event in `sw-entity-multi-select`
* Removed `input` event in `sw-entity-single-select`
* Removed `popoverConfig` property in `sw-select-result-list`
* Removed `popoverConfig` property in `sw-single-select`
* Removed `popoverConfig` property in `sw-entity-single-select`
* Removed `enableInheritance` property in `sw-price-field`
* Removed `popoverConfigExtension` property in `utils/sw-popover`
* Removed `onDefaultItemAdd` function in `sw-sales-channel/view/sw-sales-channel-detail-base`
* We fixed a bug where inherited themes were not detected correctly from `ThemeNamespaceHierarchyBuilder`
* Introduced new SCSS variable `$order-grid-gutter-width` with the value of `20px`.	
* In the `RouteRequestEvent` classes the criteria object was added to extend route DAL query. Adjustments to the request payload to extend the query are no longer necessary.
* Fixed filter dropdown are cut off, added `data-boundary="viewport"` into the button `filter-panel-item-toggle`
    * `platform/src/Storefront/Resources/views/storefront/component/listing/filter/filter-multi-select.html.twig`
    * `platform/src/Storefront/Resources/views/storefront/component/listing/filter/filter-range.html.twig`
    * `platform/src/Storefront/Resources/views/storefront/component/listing/filter/filter-rating.html.twig`
* Added a namespace variable to sw_icon to allow adding storefront icons
* Deprecated the following blocks, since they've been replaced by the new variant characteristics display:
    * `component_offcanvas_product_variants` in `src/Storefront/Resources/views/storefront/component/checkout/offcanvas-item.html.twig`
    * `page_checkout_item_info_variants` in `src/Storefront/Resources/views/storefront/page/checkout/checkout-item.html.twig`
* Fix sw_sanitize filter throwing when the parameter options is null
* Deprecated twig variable `accounTypeRequired` in `address-form.html.twig`, use `accountTypeRequired` instead
* Fixed property sorting for multi language shops
* Added an additional class to the cart offcanvas called `cart-offcanvas`
* Added all language flags according to language packs
* Deprecated global `apiAccessUrl`
* Deprecated `StoreApiClient`, use storefront controller instead
* Deprecated `Shopware\Storefront\Controller\CsrfController::getApiAccess`, use storefront controller instead
