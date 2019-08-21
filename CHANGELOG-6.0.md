CHANGELOG for 6.0.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 6.0 minor and early access versions.

To get the diff for a specific change, go to https://github.com/shopware/platform/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/shopware/platform/compare/v6.0.0+dp1...v6.0.0+ea1

* 6.0.0 EA1 (2019-07-17)

[View all changes from v6.0.0+dp1...v6.0.0+ea1](https://github.com/shopware/platform/compare/v6.0.0+dp1...v6.0.0+ea1)

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
* Storefront
    * Changed the default storefront script path in `Bundle` to `Resources/dist/storefront/js`
    * Changed the name of `messages.<locale>.json` to `storefront.<locale>.json` and changed to **not** be a base file anymore.
**Removals**

* Removed `\Shopware\Core\Checkout\Customer\SalesChannel\AddressService::getCountryList` function
* Removed `\Shopware\Core\Framework\DataAbstractionLayer\Search\PaginationCriteria`
* Removed `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::addAssociationPath` use `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::addAssociation` instead
