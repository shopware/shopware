---
title: Refactor First Run Wizard client
issue: NEXT-24068
author: Frederik Schmitt
author_email: f.schmitt@shopware.com
author_github: fschmtt
---
# Core
* Added `Shopware\Core\Framework\Store\Services\FirstRunWizardService` as a domain layer between the `FirstRunWizardController` and the `FirstRunWizardClient`
* Changed `Shopware\Core\Framework\Store\Services\FirstRunWizardClient` to only be responsible for HTTP communication
* Changed `Shopware\Core\Framework\Store\Api\FirstRunWizardController` to use the new `FirstRunWizardService`
