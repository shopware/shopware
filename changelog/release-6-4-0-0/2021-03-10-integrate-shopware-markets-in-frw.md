---
title: Integrate Shopware Markets in FRW
issue: NEXT-14129
---
# Administration
* Added twig block `sw_first_run_wizard_markets` to `src/Administration/Resources/app/administration/src/module/sw-first-run-wizard/view/sw-first-run-wizard-markets/sw-first-run-wizard-markets.html.twig`
* Added snippet `sw-first-run-wizard.markets.modalTitle`
* Added snippet `sw-first-run-wizard.markets.heading`
* Added snippet `sw-first-run-wizard.markets.description`
* Added snippet `sw-first-run-wizard.markets.footnote`
* Added snippet `sw-first-run-wizard.stepItemTitle.markets`
* Added file `src/Administration/Resources/app/administration/static/img/sw-markets/markets_illustration.svg`
* Deprecated method `downloadPlugin` in `src/Administration/Resources/app/administration/src/core/service/api/store.api.service.js`
* Added method `downloadAndUpdatePlugin` in `src/Administration/Resources/app/administration/src/core/service/api/store.api.service.js`
___
# Upgrade Information

## Store api service
Deprecated method `downloadPlugin` if you're using it to install **and** update a plugin then use `downloadAndUpdatePlugin`.
In the future `downloadPlugin` will only download plugins.
