UPGRADE FROM 6.2.x to 6.3
=======================

Table of content
----------------

* [Core](#core)
* [Administration](#administration)

Core
----

* Deprecated configuration `api.allowed_limits` in `src/Core/Framework/DependencyInjection/Configuration.php`
* Removed deprecations:
    * Removed deprecated property `allowedLimits` and method `getAllowedLimits` in `Shopware\Core\Framework\DataAbstractionLayer\Search/RequestCriteriaBuilder.php`
    * Removed deprecated configuration `api.allowed_limits` in `src/Core/Framework/Resources/config/packages/shopware.yaml`
    * Removed class `Shopware\Core\Framework\DataAbstractionLayer\Exception\DisallowedLimitQueryException`

Administration
--------------

* Removed LanguageStore
    * Use Context State instead
    * Replace `languageStore.setCurrentId(this.languageId)` with `Shopware.State.commit('context/setApiLanguageId', languageId)`
    * Replace `languageStore.getCurrentId()` with `Shopware.Context.api.languageId`
    * Replace `getCurrentLanguage` with the Repository
    * Removed `getLanguageStore`
    * Replace `languageStore.systemLanguageId` with `Shopware.Context.api.systemLanguageId`
    * Replace `languageStore.currentLanguageId` with `Shopware.Context.api.languageId`
    * Removed `languageStore.init`
    * Added mutation to Context State: `setApiLanguageId`
    * Added mutation to Context State: `resetLanguageToDefault`
    * Added getter to Context State: `isSystemDefaultLanguage`
