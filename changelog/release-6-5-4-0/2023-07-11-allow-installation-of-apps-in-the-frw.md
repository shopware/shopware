---
title: Allow installation of apps in the FRW
issue: NEXT-26389
author: Frederik Schmitt
author_email: f.schmitt@shopware.com
author_github: fschmtt
---
# Core
* Changed `Shopware\Core\Framework\Store\Api\FirstRunWizardController` to pass installed apps to the `Shopware\Core\Framework\Store\Services\FirstRunWizardService`
* Changed `Shopware\Core\Framework\Store\Services\FirstRunWizardService` to additionally match recommendations against installed apps
* Changed `\Shopware\Core\Framework\Store\Struct\StorePluginStruct` to contain the extension's type
___
# Administration
* Changed `src/module/sw-first-run-wizard/component/sw-plugin-card/index.ts` to allow installation of apps
* Changed `src/module/sw-first-run-wizard/view/sw-first-run-wizard-plugins/sw-first-run-wizard-plugins.html.twig` to refresh recommendations after installing an extension

