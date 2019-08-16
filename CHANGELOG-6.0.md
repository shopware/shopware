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
* Core
    * Added DAL support for multi primary keys. 
    * Added API endpoints for translation definitions
    * Added new event `\Shopware\Core\Content\Category\Event\NavigationLoadedEvent` which dispatched after a sales channel navigation loaded
    * Added restriction to storefront API to prevent filtering, sorting, aggregating and association loading of ReadProtected fields/entities
    * Added `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::addAssociations` which allows to add multiple association paths
    * Changed signature of `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::addAssociation`
      The second parameter `$criteria` has been removed. Use `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::getAssociation` instead.  
    * Changed the name of `core.<locale>.json` to `messages.<locale>.json` and changed to new base file.
    * Changed name of property in CurrencyDefinition from `isDefault` to `isSystemDefault`
    * Added RouteScopes as required Annotation for all Routes
* Storefront
    * Changed the default storefront script path in `Bundle` to `Resources/dist/storefront/js`
    * Changed the name of `messages.<locale>.json` to `storefront.<locale>.json` and changed to **not** be a base file anymore.
    * Added `extractIdsToUpdate` to `Shopware\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlRouteInterface`
**Removals**

* Removed `\Shopware\Core\Checkout\Customer\SalesChannel\AddressService::getCountryList` function
* Removed `\Shopware\Core\Framework\DataAbstractionLayer\Search\PaginationCriteria`
* Removed `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::addAssociationPath` use `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::addAssociation` instead
