---
title: Add locale code to every app request
issue: NEXT-15838
---
# Core
* Added new class `Shopware\Core\Framework\App\AppLocaleProvider` for handle get Locale code from context languageId
* Added new param option request `app_request_context` at `src/Core/Framework/App/Hmac/Guzzle/AuthMiddleware.php` for handle get language header request
* Added param `sw-context-language` represent for shopware language id, and `sw-user-language` represent for locale of the user or locale of the context language to every App request header, the params will be added from 6.4.5.0 onwards:
  * Changed at method `src/Core/Framework/App/ActionButton/Executor::execute`
  * Changed at method `src/Core/Framework/App/Lifecycle/Registration/AppRegistrationService::registerApp`
  * Changed at method `src/Core/Framework/App/Payment/Payload/PayloadService::request`
  * Changed at method `src/Core/Framework/Webhook/WebhookDispatcher::dispatch`
* Added sw-language into the query of some requests below:
  * Changed at method`src/Core/Framework/App/Manifest/ModuleLoader::loadModules`
