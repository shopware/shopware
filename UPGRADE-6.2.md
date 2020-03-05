UPGRADE FROM 6.1.x to 6.2
=======================

Table of content
----------------

* [Core](#core)
* [Administration](#administration)
* [Storefront](#storefront)
* [Refactorings](#refactorings)

Core
----

* The usage of `entity` in the `shopware.entity.definition` tag is deprecated and will be removed with 6.4.
    * Therefore change:
        `<tag name="shopware.entity.definition" entity="product"/>`
      To:
        `<tag name="shopware.entity.definition"/>`
    * As a fallback, this function is used first 
* We deprecated the `LongTextWithHtmlField` in 6.2, use `LongTextField` with `AllowHtml` flag instead
* The Mailer is not overwritten anymore, instead the swiftmailer.transport is decorated.
    * Therefore the MailerFactory returns a Swift_Transport Instance instead of Swift_Mailer
    * The MailerFactory::create Method is deprecated now

    Before: 
    ```
    new LongTextWithHtmlField('content', 'content')
    ```
  
    After:  
    ```
    (new LongTextField('content', 'content'))->addFlags(new AllowHtml()
    ```
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
    * Define permissions for the Recalculation at class `OrderConverter` within the array constant `ADMIN_EDIT_ORDER_PERMISSIONS`.
    * Extended permissions with subscribe event `SalesChannelContextPermissionsChangedEvent`, see detail at class `SalesChannelContextFactory`
    
* The usage of `$connection->executeQuery()` for write operations is deprecated, use 
`$connection->executeUpdate()` instead.
* For the possibility to add individual product to across selling, you need to use a new field for creating a cross selling
    * Please use type `productList` or `productStream` in order to create a corresponding cross selling
   

Administration
--------------

* `sw-settings-custom-field-set`
    - Removed method which overrides the mixin method `getList`, use the computed `listingCriteria` instead
    - Add computed property `listingCriteria`
* `sw-settings-document-list`
    - Removed method which overrides the mixin method `getList`, use the computed `listingCriteria` instead
    - Add computed property `listingCriteria`
* Refactor  `sw-settings-snippet-list`
    - Removed `StateDeprecated`
    - Remove computed property `snippetSetStore`, use `snippetSetRepository' instead
    - Add computed property `snippetSetRepository`
    - Add computed property `snippetSetCriteria`
* Refactor `sw-settings-snippet-set-list`
    - Remove `StateDeprecated`
    - Remove computed property `snippetSetStore`, use `snippetSetRepository' instead
    - Add computed property `snippetSetCriteria`
    - The method `onConfirmClone` is now an asynchronous method
* Refactor mixin `sw-settings-list.mixin`
    - Remove `StateDeprecated`
    - Remove computed property `store`, use `entityRepository` instead
    - Add computed property `entityRepository`
    - Add computed property `listingCriteria`
* The component sw-plugin-box was refactored to use the "repositoryFactory" instead of "StateDeprecated" to fetch and save data
        - removed "StateDeprecated"
        - removed computed "pluginStore" use "pluginRepository" instead
* The component sw-settings-payment-detail was refactored to use the "repositoryFactory" instead of "StateDeprecated" to fetch and save data
    - removed "StateDeprecated"
    - removed computed "paymentMethodStore" use "paymentMethodRepository" instead
    - removed computed "ruleStore" use "ruleRepository" instead
    - removed computed "mediaStore" use "mediaRepository" instead
* Refactor the module `sw-settings-number-range-detail`
    * Remove LocalStore
    * Remove StateDeprecated
    * Remove data typeCriteria
    * Remove data numberRangeSalesChannelsStore
    * Remove data numberRangeSalesChannels
    * Remove data numberRangeSalesChannelsAssoc
    * Remove data salesChannelsTypeCriteria
    * Remove computed numberRangeStore use numberRangeRepository instead
    * Remove computed firstSalesChannel
    * Remove computed salesChannelAssociationStore
    * Remove computed numberRangeStateStore use numberRangeStateRepository instead
    * Remove computed salesChannelStore use salesChannelRepository instead
    * Remove computed numberRangeTypeStore use numberRangeTypeRepository instead
    * Remove method onChange
    * Remove method showOption
    * Remove method getPossibleSalesChannels
    * Remove method setSalesChannelCriteria
    * Remove method enrichAssocStores
    * Remove method onChangeSalesChannel
    * Remove method configHasSaleschannel
    * Remove method selectHasSaleschannel
    * Remove method undeleteSaleschannel

* Refactored `sw-newsletter-recipient-list`
    * Removed LocalStore
    * Removed StateDeprecated
    * Removed Computed salesChannelStore, use salesChannelRepository instead
    * Removed Computed tagStore, use tagRepository instead
    * Removed Computed tagAssociationStore

* Refactored mapErrorService
    * Deprecated `mapApiErrors`, use `mapPropertyErrors`
    * Added `mapCollectionPropertyErrors` to mapErrorService for Entity Collections
* Deprecated `sw-multi-ip-select` with version 6.4, use the `sw-multi-tag-ip-select`-component instead
* Replaced Store based datahandling with repository based datahandling in media specific components and modules, including the following changes
    * sw-media-field is deprecated and replaced by sw-media-field-v2
        * Added injection of `repositoryFactory`
        * Replaced computed property `mediaStore` with `mediaRepository`
        * Method `fetchItem` is now async
        * Method `fetchSuggestions` is now async
    * sw-media-list-selection is deprecated and replaced by sw-media-list-selection-v2
        * Added injection of `repositoryFactory`
        * Added injection of `mediaService`
        * Replaced computed property `mediaStore` with `mediaRepository`
        * Method `onUploadsAdded` is now async
        * Method `successfulUpload` is now async
    * sw-media-preview is deprecated and replaced by sw-media-preview-v2
        * Added injection of `repositoryFactory`
        * Replaced computed property `mediaStore` with `mediaRepository`
        * Method `fetchSourceIfNecessary` is now async
        * Method `getDataUrlFromFile` is now async
    * sw-media-upload is deprecated and replaced sw-media-upload-v2
        * Added injection of `repositoryFactory`
        * Added injection of `mediaService`
        * Replaced computed property `defaultFolderStore` with `defaultFolderRepository`
        * Watcher of prop `defaultFolder` is now async
        * Method `createdComponent` is now async
        * Method `onUrlUpload` is now async
        * Method `handleUpload` is now async
        * Method `getDefaultFolderId` is now async
        * Replaced method `handleUploadStoreEvent` with `handleMediaServiceUploadEvent`
    * sw-duplicated-media is deprecated and replaced sw-duplicated-media-v2 
        * Added injection of `repositoryFactory`
        * Replaced computed property `mediaStore` with `mediaRepository`
        * Replaced method `handleUploadStoreEvent` with `handleMediaServiceUploadEvent`
        * Method `updatePreviewData` is now async
        * Method `renameFile` is now async
        * Method `skipFile` is now async
        * Method `replaceFile` is now async
    * sw-media-modal is deprecated and replaced by sw-media-modal-v2
        * Added injection of `repositoryFactory`
        * Added injection of `mediaService`
        * Replaced computed property `mediaStore` with `mediaRepository`
        * Replaced computed property `mediaFolderStore` with `mediaFolderRepository`
        * Method `fetchCurrentFolder` is now async
        * Method `onUploadsAdded` is now async
        * Method `onUploadsFinished` is now async
    * sw-upload-store-listener  is deprecated and replaced by sw-upload-listener
        * Added injection of `repositoryFactory`
        * Added injection of `mediaService`
        * Added computed property `mediaRepository`
        * Method `handleError` is now async
    * sw-media-compact-upload is deprecated and replaced by sw-media-compact-upload-v2
* Deprecated method `getFields`, use `getStructuredFields` instead

* Refactored settings items list
    * Deprecated `sw_settings_content_card_slot_plugins` twig block with version 6.4
    * If your Plugin extends `src/module/sw-settings/page/sw-settings-index/sw-settings-index.html.twig`
      to appear in the "plugins" tab on settings page, 
      remove the extension from your plugin and add an `settingsItem` Array to your Module instead:
      
        ```
        Module.register('my-awesome-plugin') {
            // ...
            settingsItem: [
                {
                    name:   'my-awesome-plugin',             // unique name
                    to:     'my.awesome.plugin.index',       // dot notated route to your plugin's settings index page  
                    label:  'my.awesome.plugin.title',       // translation snippet key
                    group:  'plugins',                       // register plugin under "plugins" tab in settings page
                    icon:   'use a shopware icon here',
                    // OR
                    component: 'component'                   // use a component here, if you want to render the icon in any other way            
                }
                // ... more settings items
            ]
        }
        ```
* Implemented the possibility to add individual product to cross selling
    * Added `sw-product-cross-selling-assignment` component

* CustomFields are now sorted naturally when custom position is used with customFieldPosition (for example 1,9,10 instead of 1,10,9)
* The id of checkbox and switch fields are now unique. Therefore you have to update your selectors if you want to get the fields by id.
This was an important change, because every checkbox and switch field has the same id. This causes problems when you click
on the corresponding label.
 
Storefront
----------

* We removed the SCSS skin import `@import 'skin/shopware/base'` inside `/Users/tberge/www/sw6/platform/src/Storefront/Resources/app/storefront/src/scss/base.scss`.
    * If you don't use the `@Storefront` bundle in your `theme.json` and you are importing the shopware core `base.scss` manually you have to import the shopware skin too in order to get the same result:

        Before
        ```
        @import "../../../../vendor/shopware/platform/src/Storefront/Resources/app/storefront/src/scss/base.scss";
        ```

        After
        ```
        @import "../../../../vendor/shopware/platform/src/Storefront/Resources/app/storefront/src/scss/base.scss";
        @import "../../../../vendor/shopware/platform/src/Storefront/Resources/app/storefront/src/scss/skin/shopware/base";
* We changed the storefront ESLint rule `comma-dangle` to `never`, so that trailing commas won't be forcefully added anymore
* The theme manager supports now tabs. Usage: 
```json
"config": {
    "tabs": {
        "colors": {
            "label": {
                "en-GB": "Colours",
                "de-DE": "Farben"
            }
        },
    },
    "blocks": {
    ...
    },
    ...,
    "fields": {
        "sw-color-brand-primary": {
            "label": {
                "en-GB": "Primary colour",
                "de-DE": "Primärfarbe"
            },
            "type": "color",
            "value": "#008490",
            "editable": true,
            "block": "themeColors",
            "tab": "colors",
            "order": 100
        },
        ...
    }
}
```

When you don´t specify a tab then it will be shown in the main tab.

* Added Twig Filter `replace_recursive` for editing values in nested Arrays. This allows for editing
js options in Twig:

```twig
{% set productSliderOptions = {
    productboxMinWidth: sliderConfig.elMinWidth.value ? sliderConfig.elMinWidth.value : '',
    slider: {
        gutter: 30,
        autoplayButtonOutput: false,
        nav: false,
        mouseDrag: false,
        controls: sliderConfig.navigation.value ? true : false,
        autoplay: sliderConfig.rotate.value ? true : false
    }
} %}

{% block element_product_slider_slider %}
    <div class="base-slider"
         data-product-slider="true"
         data-product-slider-options="{{ productSliderOptions|json_encode }}">
    </div>
{% endblock %}
```

Now the variable can be overwritten with `replace_recursive`:

```twig
{% block element_product_slider_slider %}
    {% set productSliderOptions = productSliderOptions|replace_recursive({
        slider: {
            mouseDrag: true
        }
    }) %}

    {{ parent() }}
{% endblock %}
```

Refactorings
------------



