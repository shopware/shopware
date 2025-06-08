---
title: remove php deprecations in the core for merchant services area
issue: NEXT-24646
author: Adrian Les
author_email: a.les@shopware.com
author_github: adrianles
---
# Core
* Changed `Shopware/Core/Framework/Store/Services/StoreAppLifecycleService`. The class is now internal.
* Changed `Shopware/Core/Framework/Store/Services/StoreClient`. The unused method `getLicenses()` has been removed.
* Changed `Shopware/Core/Framework/Store/Services/StoreClientFactory`. The class is now internal and the unused method `getAppIdByName()` has been removed.
* Changed `Shopware/Core/Framework/Store/Services/InstanceService`. The class is now internal.
* Changed `Shopware/Core/Framework/Store/Services/AbstractExtensionStoreLicensesService`. The class is now internal.
* Changed `Shopware/Core/Framework/Store/Services/AbstractExtensionDataProvider`. The class is now internal.
* Changed `Shopware/Core/Framework/Store/Exception/ExtensionUpdateRequiresConsentAffirmationException`. The class now extends `ShopwareHttpException`.
* Removed `Shopware/Core/Framework/Store/Exception/ExtensionRequiresNewPrivilegesException`.
* Changed `Shopware/Core/Framework/Store/Event/FirstRunWizardStartedEvent`. The class is now internal.
* Changed `Shopware/Core/Framework/Store/Event/FirstRunWizardFinishedEvent`. The class is now internal.
* Changed `Shopware/Core/Framework/Store/Command/StoreDownloadCommand`. The class is now internal.
* Changed `Shopware/Core/Framework/Store/Command/StoreLoginCommand`. The class is now internal and the ´language´ parameter has been removed.
* Removed `Shopware/Core/Framework/Store/Authentication/AuthenticationProvider` and `Shopware/Core/Framework/Store/Authentication/AbstractAuthenticationProvider` and their tests.
* Changed `Shopware/Core/Framework/Store/Authentication/AbstractStoreRequestOptionsProvider`. The class is now internal and the specification of the method `getDefaultQueryParameters()` has changed.
* Changed `Shopware/Core/Framework/Store/Authentication/FrwRequestOptionsProvider`. The class is now internal and the specification of the method `getDefaultQueryParameters()` has changed.
* Changed `Shopware/Core/Framework/Store/Authentication/LocaleProvider`. The class is now internal.
* Changed `Shopware/Core/Framework/Store/Authentication/StoreRequestOptionsProvider`. The class is now internal and the specification of the method `getDefaultQueryParameters()` has changed and some tests have been removed.
* Removed `Shopware/Core/Framework/Store/Api/AbstractStoreController`.
* Changed `Shopware/Core/Framework/Store/Api/StoreController` and `Shopware/Core/Framework/Store/Services/StoreClient`. The API entry points `pingStoreAPI()`, `getLicenseList()` and `downloadPlugin()` have been removed.
