UPGRADE FROM 6.1.x to 6.2
=======================

Core
----


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

Storefront
----------

