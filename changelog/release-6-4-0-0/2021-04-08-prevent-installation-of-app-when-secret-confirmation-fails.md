---
title: Prevent installation of app when secret confirmation fails
issue: NEXT-14388
---
# Core
* Changed `\Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService::registerApp` to reject app installation when confirmation request fails
