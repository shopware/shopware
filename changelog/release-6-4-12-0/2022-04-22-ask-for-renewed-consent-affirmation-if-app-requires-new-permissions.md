---
title: Ask for renewed consent affirmation if app requires new permissions
issue: NEXT-19353
author: Frederik Schmitt
author_email: f.schmitt@shopware.com
author_github: fschmtt
---
# Core
* Added new `Shopware\Core\Framework\App\Delta\DomainsDeltaProvider` to determine domains delta of updatable apps 
* Added parameter `bool $allowNewPermissions` to `Shopware\Core\Framework\Store\Services\AbstractExtensionLifecycle::updateExtension()`
* Added parameter `bool $allowNewPermissions` to `Shopware\Core\Framework\Store\Services\ExtensionLifecycle::updateExtension()`
* Changed parameter `$allowNewPrivilges` of `Shopware\Core\Framework\Store\Services\AbstractStoreAppLifecycleService::updateExtension()` to `$allowNewPermissions`
* Changed parameter `$allowNewPrivilges` of `Shopware\Core\Framework\Store\Services\StoreAppLifecycleService::updateExtension()` to `$allowNewPermissions`
* Changed method `Shopware\Core\Framework\App\Delta\PermissionsDeltaProvider::getReport()` to return report even if no delta was detected
___
# API
* Changed route `api.extension.update` to consider consent affirmation before performing an extension update
___
# Administration
* Added data `showConsentAffirmationModal` and `consentAffirmationDeltas` to `src/module/sw-extension/component/sw-extension-card-base/index.js`
* Added computed properties `consentAffirmationModalActionLabel` and `consentAffirmationModalTitle` to `src/module/sw-extension/component/sw-extension-card-base/index.js`
* Added parameter `allowNewPermissions` to method `updateExtension` to consider consent affirmation in `src/module/sw-extension/component/sw-extension-card-base/index.js`
* Added methods `openConsentAffirmationModal`, `closeConsentAffirmationModal` and `closeConsentAffirmationModalAndUpdateExtension` to `src/module/sw-extension/component/sw-extension-card-base/index.js`
* Added block `sw_extension_card_base_consent_affirmation_modal` to `src/module/sw-extension/component/sw-extension-card-base/sw-extension-card-base.html.twig`
* Added properties `closeLabel` and `title` to `src/module/sw-extension/component/sw-extension-permissions-modal/index.js`
* Added slot `intro-text` to `src/module/sw-extension/component/sw-extension-permissions-modal/sw-extension-permissions-modal.html.twig`
* Added parameter `allowNewPermissions` to method `updateExtension` to consider consent affirmation in `src/module/sw-extension/service/extension-store-action.service.ts` 
* Added parameter `allowNewPermissions` to method `updateExtension` to consider consent affirmation in `src/module/sw-extension/service/shopware-extension.service.ts` 
