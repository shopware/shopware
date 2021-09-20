---
title: Add repository iterator in administration to improve loading entities
issue: NEXT-10262
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Added new method `iterateIds` in `src/Core/Framework/DataAbstractionLayer/Dbal/Common/RepositoryIterator.php` to allow easier iterating over `fetchIds`
* Added new method `iterate` in `src/Core/Framework/DataAbstractionLayer/Dbal/Common/RepositoryIterator.php` to allow easier iterating over `fetch`
___
# Administration
* Added `RepositoryIterator` to `Shopware.Data` that replicates PHP `Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator`
* Added `TotalCountMode` to `Shopware.Data`
* Changes loading entities to use request iteration in method `showSearch` in `src/Administration/Resources/app/administration/src/app/component/base/sw-property-search/index.js`
* Changes loading entities to use request iteration in method `loadGroups` in `src/Administration/Resources/app/administration/src/app/component/base/sw-property-search/index.js`
* Changes loading entities to use request iteration in method `loadOptions` in `src/Administration/Resources/app/administration/src/app/component/base/sw-property-search/index.js`
* Changes loading entities to use request iteration in method `groupProperties` in `src/Administration/Resources/app/administration/src/app/component/base/sw-property-assignment/index.js`
* Changes loading entities to use request iteration in method `getTreeItems` in `src/Administration/Resources/app/administration/src/app/component/entity/sw-category-tree-field/index.js`
* Changes loading entities to use request iteration in method `searchCategories` in `src/Administration/Resources/app/administration/src/app/component/entity/sw-category-tree-field/index.js`
* Changes loading entities to use request iteration in method `getSubFolders` in `src/Administration/Resources/app/administration/src/app/component/media/sw-media-folder-content/index.js`
* Changes loading entities to use request iteration in method `createdComponent` in `src/Administration/Resources/app/administration/src/app/component/media/sw-media-modal-folder-settings/index.js`
* Changes loading entities to use request iteration in method `getSubFolders` in `src/Administration/Resources/app/administration/src/app/component/media/sw-sidebar-media-item/index.js`
* Changes loading entities to use request iteration in method `createdComponent` in `src/Administration/Resources/app/administration/src/app/component/structure/sw-sales-channel-config/index.js`
* Changes loading entities to use request iteration in method `checkForUpdates` in `src/Administration/Resources/app/administration/src/core/service/customer-group-registration-listener.service.js`
* Changes loading entities to use request iteration in method `loadRootCategories` in `src/Administration/Resources/app/administration/src/module/sw-category/component/sw-category-tree/index.js`
* Changes loading entities to use request iteration in method `loadCustomFieldSet` in `src/Administration/Resources/app/administration/src/module/sw-category/page/sw-category-detail/index.js`
* Changes loading entities to use request iteration in method `createdComponent` in `src/Administration/Resources/app/administration/src/module/sw-cms/page/sw-cms-detail/index.js`
* Changes loading entities to use request iteration in method `onChangeLanguage` in `src/Administration/Resources/app/administration/src/module/sw-cms/page/sw-cms-detail/index.js`
* Changes loading entities to use request iteration in method `createdComponent` in `src/Administration/Resources/app/administration/src/module/sw-customer/view/sw-customer-detail-addresses/index.js`
* Changes loading entities to use request iteration in method `refreshList` in `src/Administration/Resources/app/administration/src/module/sw-customer/view/sw-customer-detail-order/index.js`
* Changes loading entities to use request iteration in method `createdComponent` in `src/Administration/Resources/app/administration/src/module/sw-import-export/component/sw-import-export-edit-profile-modal-mapping/index.js`
* Changes loading entities to use request iteration in method `getCustomFieldSets` in `src/Administration/Resources/app/administration/src/module/sw-media/component/sidebar/sw-media-quickinfo/index.js`
* Changes loading entities to use request iteration in method `createdComponent` in `src/Administration/Resources/app/administration/src/module/sw-newsletter-recipient/page/sw-newsletter-recipient-list/index.js`
* Changes loading entities to use request iteration in method `loadFilters` in `src/Administration/Resources/app/administration/src/module/sw-product-stream/page/sw-product-stream-detail/index.js`
* Changes loading entities to use request iteration in method `getProductCustomFields` in `src/Administration/Resources/app/administration/src/module/sw-product-stream/page/sw-product-stream-detail/index.js`
* Changes loading entities to use request iteration in method `getChildrenIds` in `src/Administration/Resources/app/administration/src/module/sw-product/component/sw-product-clone-modal/index.js`
* Changes loading entities to use request iteration in method `getProductStreamFilter` in `src/Administration/Resources/app/administration/src/module/sw-product/component/sw-product-cross-selling-form/index.js`
* Changes loading entities to use request iteration in method `loadCurrencies` in `src/Administration/Resources/app/administration/src/module/sw-product/component/sw-product-variants/sw-product-variants-configurator/sw-product-variants-configurator-prices/index.js`
* Changes loading entities to use request iteration in method `loadCurrencies` in `src/Administration/Resources/app/administration/src/module/sw-product/page/sw-product-detail/index.js`
* Changes loading entities to use request iteration in method `loadTaxes` in `src/Administration/Resources/app/administration/src/module/sw-product/page/sw-product-detail/index.js`
* Changes loading entities to use request iteration in method `getList` in `src/Administration/Resources/app/administration/src/module/sw-product/page/sw-product-list/index.js`
* Changes loading entities to use request iteration in method `mountedComponent` in `src/Administration/Resources/app/administration/src/module/sw-product/view/sw-product-detail-context-prices/index.js`
* Changes loading entities to use request iteration in method `loadAssignedProducts` in `src/Administration/Resources/app/administration/src/module/sw-product/view/sw-product-detail-cross-selling/index.js`
* Changes loading entities to use request iteration in method `loadGroups` in `src/Administration/Resources/app/administration/src/module/sw-product/view/sw-product-detail-variants/index.js`
* Changes loading entities to use request iteration in method `loadLanguages` in `src/Administration/Resources/app/administration/src/module/sw-profile/page/sw-profile-index/index.js`
* Changes loading entities to use request iteration in method `loadExclusions` in `src/Administration/Resources/app/administration/src/module/sw-promotion/component/sw-promotion-basic-form/index.js`
* Changes loading entities to use request iteration in method `loadSetGroups` in `src/Administration/Resources/app/administration/src/module/sw-promotion/component/sw-promotion-cart-condition-form/index.js`
* Changes loading entities to use request iteration in method `createdComponent` in `src/Administration/Resources/app/administration/src/module/sw-promotion/component/sw-promotion-discount-component/index.js`
* Changes loading entities to use request iteration in method `createdComponent` in `src/Administration/Resources/app/administration/src/module/sw-promotion/component/sw-promotion-sales-channel-select/index.js`
* Changes loading entities to use request iteration in method `reloadCustomers` in `src/Administration/Resources/app/administration/src/module/sw-promotion/service/persona-customer-grid.service.js`
* Changes loading entities to use request iteration in method `loadEntityData` in `src/Administration/Resources/app/administration/src/module/sw-sales-channel/component/structure/sw-sales-channel-menu/index.js`
* Changes loading entities to use request iteration in method `createdComponent` in `src/Administration/Resources/app/administration/src/module/sw-sales-channel/component/sw-sales-channel-modal-grid/index.js`
* Changes loading entities to use request iteration in method `loadCustomFieldSets` in `src/Administration/Resources/app/administration/src/module/sw-sales-channel/page/sw-sales-channel-detail/index.js`
* Changes loading entities to use request iteration in method `loadStorefrontDomains` in `src/Administration/Resources/app/administration/src/module/sw-sales-channel/view/sw-sales-channel-detail-base/index.js`
* Changes loading entities to use request iteration in method `loadSeoUrls` in `src/Administration/Resources/app/administration/src/module/sw-settings-customer-group/page/sw-settings-customer-group-detail/index.js`
* Changes loading entities to use request iteration in method `loadAvailableSalesChannel` in `src/Administration/Resources/app/administration/src/module/sw-settings-document/page/sw-settings-document-detail/index.js`
* Changes loading entities to use request iteration in method `_fetchEntities` in `src/Administration/Resources/app/administration/src/module/sw-settings-product-feature-sets/service/feature-grid-translation.service.js`
* Changes loading entities to use request iteration in method `loadConditions` in `src/Administration/Resources/app/administration/src/module/sw-settings-rule/page/sw-settings-rule-detail/index.js`
* Changes loading entities to use request iteration in method `initSalesChannelCollection` in `src/Administration/Resources/app/administration/src/module/sw-settings-seo/component/sw-seo-url/index.js`
* Changes loading entities to use request iteration in method `loadCurrencies` in `src/Administration/Resources/app/administration/src/module/sw-settings-shipping/page/sw-settings-shipping-detail/index.js`
* Changes counting entities to use count request in method `checkIfPropertiesExists` in `src/Administration/Resources/app/administration/src/module/sw-product/view/sw-product-detail-properties/index.js`
* Changes counting entities to use count request in method `createdComponent` in `src/Administration/Resources/app/administration/src/module/sw-sales-channel/component/sw-sales-channel-modal/index.js`
* Changes counting entities to use count request in method `onChangeFileNameDebounce` in `src/Administration/Resources/app/administration/src/module/sw-sales-channel/view/sw-sales-channel-detail-base/index.js`
* Changes counting entities to use count request in method `isCustomFieldNameUnique` in `src/Administration/Resources/app/administration/src/module/sw-settings-custom-field/component/sw-custom-field-list/index.js`
* Changes counting entities to use count request in method `onSave` in `src/Administration/Resources/app/administration/src/module/sw-settings-custom-field/page/sw-settings-custom-field-set-create/index.js`
* Changes counting entities to use count request in method `onChangeDebounce` in `src/Administration/Resources/app/administration/src/module/sw-settings-salutation/page/sw-settings-salutation-detail/index.js`
___
# Upgrade Information

## Administration entity list loading

When you load entities in the administration via a repository from the repositoryFactory service you will send a single request you will get a page of entities:
```javascript
repositoryFactory.create('product')
    .search(new Shopware.Data.Criteria())
    .then(products => {
        console.log(products);
    });
```

Now you can use the repository built-in repository iterator factory methods to get all pages of the entity without changing lots of your code:
```javascript
repositoryFactory.create('product')
    .iterate()
    .then(products => {
        console.log(products);
    });
```

`iterate` can still receive a criteria which is useful to determine the page size and filters as well as a context object as parameters.
