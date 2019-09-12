CHANGELOG for 6.0.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 6.0 minor and early access versions.

To get the diff for a specific change, go to https://github.com/shopware/platform/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/shopware/platform/compare/v6.0.0+dp1...v6.0.0+ea1

### 6.0.0 EA1 (2019-07-17)

**Additions / Changes**

* Added `JoinBuilderInterface` and moved join logic from `FieldResolver` into `JoinBuilder`
* Added getJoinBuilder to `FieldResolverInterface`
* Fixed Twig template loading for the theme system. Twig files from themes will only be loaded if the theme is active
 for the requested sales channel.
* Added `active` column to `theme` entity.
* Improved theme lifecycle handling. Themes will be set to inactive if deactivated/uninstalled. Config will be reloaded
  when a theme is updated. Themes will automatically be recompiled if the plugin is updated.
* Improved loading/refresh of theme.json. You can now change the theme.json and use `bin/console theme:refresh` to
  reload the configuration.
[View all changes from v6.0.0+dp1...v6.0.0+ea1](https://github.com/shopware/platform/compare/v6.0.0+dp1...v6.0.0+ea1)
* Added Twig filters `sw_encode_url` and `sw_encode_media_url` to Storefront. Contrary to Twig's `url_encode` filter it encodes every segment of the path rather than the whole url string.
You can use them with every URL in your templates
```
{# results in http://your.domain:8080/path%20to/file%20with%20whitspace-and%28brackets%29.png #}
<img src="{{ "http://your.domain:8080/path to/file with whitspace-and(brackets).png" | sw_encode_url }}"

{# encodes the url of your media entity #}
<img src="{{ yourStorefrontMediaObject | sw_encode_media_url }} 
``` 

### 6.0.0 EA2

**Additions / Changes**

* Administration
    * Moved the global state of the module `sw-cms` to VueX
    * Renamed `sw-many-to-many-select` to `sw-entity-many-to-many-select`
    * Renamed `sw-tag-field-new` to `sw-entity-tag-select`
    * Added `sw-select-base`, `sw-select-result`, `sw-select-result-list` and `sw-select-selection-list` as base components for select fields
    * Changed select components in path `administration/src/app/component/form/select` to operate with v-model 
    * Deprecated `sw-tag-field` use `sw-entity-tag-select` instead
    * Deprecated `sw-select` use `sw-multi-select` or `sw-single-select` instead
    * Deprecated `sw-select-option` use `sw-result-option` instead
    * Moved the `sw-cms` from `State.getStore()` to `Repository` and added clientsided data resolver
    * Added translations for the `sw-cms` module
* Core
    * Added DAL support for multi primary keys. 
    * Added API endpoints for translation definitions
    * Added new event `\Shopware\Core\Content\Category\Event\NavigationLoadedEvent` which dispatched after a sales channel navigation loaded
    * Added restriction to storefront API to prevent filtering, sorting, aggregating and association loading of ReadProtected fields/entities
    * Added `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::addAssociations` which allows to add multiple association paths
    * Added `\Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField`
    * Added generic `\Shopware\Core\System\StateMachine\Api\StateMachineActionController`
    * Changed field `stateId` from `FkField` to `StateMachineStateField` in `OrderDefinition` and `OrderTransactionDefinition`
    * Changed parameter of `\Shopware\Core\System\StateMachine\StateMachineRegistry::transition`. `\Shopware\Core\System\StateMachine\Transition` is now expected.
    * Changed behaviour of `\Shopware\Core\System\StateMachine\StateMachineRegistry::transition` to now expect the action name instead of the toStateName
    * Changed signature of `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::addAssociation`
      The second parameter `$criteria` has been removed. Use `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::getAssociation` instead.  
    * Changed the name of `core.<locale>.json` to `messages.<locale>.json` and changed to new base file.
    * Changed name of property in CurrencyDefinition from `isDefault` to `isSystemDefault`
    * Added RouteScopes as required Annotation for all Routes
    * Added `\Shopware\Core\Framework\Migration\InheritanceUpdaterTrait` to update entity schema for inherited associations
* Storefront
    * Changed the default storefront script path in `Bundle` to `Resources/dist/storefront/js`
    * Changed the name of `messages.<locale>.json` to `storefront.<locale>.json` and changed to **not** be a base file anymore.
    * Added `extractIdsToUpdate` to `Shopware\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlRouteInterface`
    
**Removals**

* Administration
    * Removed `sw-tag-multi-select`
    * Removed `sw-multi-select-option` use `sw-result-option` instead
    * Removed `sw-single-select-option` use `sw-result-option` instead
* Core
    * Removed `\Shopware\Core\Checkout\Customer\SalesChannel\AddressService::getCountryList` function
    * Removed `\Shopware\Core\Framework\DataAbstractionLayer\Search\PaginationCriteria`
    * Removed `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::addAssociationPath` use `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::addAssociation` instead
    * Removed `\Shopware\Core\Checkout\Order\Api\OrderActionController` which is now replaced by the generic `\Shopware\Core\System\StateMachine\Api\StateMachineActionController`
    * Removed `\Shopware\Core\Checkout\Order\Api\OrderDeliveryActionController` which is now replaced by the generic `\Shopware\Core\System\StateMachine\Api\StateMachineActionController`
    * Removed `\Shopware\Core\Checkout\Order\Api\OrderTransactionActionController` which is now replaced by the generic `\Shopware\Core\System\StateMachine\Api\StateMachineActionController`
