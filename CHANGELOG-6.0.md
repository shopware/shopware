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

* Changed the default storefront script path in `Bundle` to `Resources/dist/storefront/js`
* Added DAL support for multi primary keys. 
* Added api endpoints for translation definitions
* Administration: Moved the global state of the module `sw-cms` to VueX
* Added new event `\Shopware\Core\Content\Category\Event\NavigationLoadedEvent` which dispatched after a sales channel navigation loaded
* Changed signature of `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::addAssociation`
    * Second parameter `$criteria` removed. Use `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::getAssociation` instead  
* Added `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::addAssociations` which allows to add multiple association paths
* Changed minor behaviour of language files:
    * The `messages.<locale>.json` of the Storefront was renamed to `storefront.<locale>.json` and changed to **not** be a base file anymore.
    * The `core.<locale>.json` of the Core was renamed to `messages.<locale>.json` and changed to be Shopware's new base file.

**Removals**

* Removed `\Shopware\Core\Checkout\Customer\SalesChannel\AddressService::getCountryList` function
* Removed `\Shopware\Core\Framework\DataAbstractionLayer\Search\PaginationCriteria`
* Removed `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::addAssociationPath` use `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::addAssociation` instead
