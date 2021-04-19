---
title: Add the current sw version to all outgoing request to external apps
issue: NEXT-14359
---
# Core
* Changed method `execute` in `src/Core/Framework/App/ActionButton/Executor.php` to add `shopware version` to `action button requests` 
* Changed method `registerWithApp` in `src/Core/Framework/App/Lifecycle/Registration/AppRegistrationService.php` to add `shopware version` to `registration request` 
* Changed method `confirmRegistration` in `src/Core/Framework/App/Lifecycle/Registration/AppRegistrationService.php` to add `shopware version` to `confirmation request`
* Changed method `assembleRequest` in `src/Core/Framework/App/Lifecycle/Registration/PrivateHandshake.php` to add `shopware version` to `registration request` 
* Changed method `assembleRequest` in `src/Core/Framework/App/Lifecycle/Registration/StoreHandshake.php` to add `shopware version` to `registration request` 
* Changed method `callWebhooks` in `src/Core/Framework/Webhook/WebhookDispatcher.php` to add `shopware version` to `webhooks request`
