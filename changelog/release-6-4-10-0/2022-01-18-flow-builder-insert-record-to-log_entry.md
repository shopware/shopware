---
title: Flow builder insert record to log_entry
issue: NEXT-19439.
---
# Core
* Added `logFlowEvent` function at `Shopware\Core\Framework\Log\LoggingService.php` which used to add the log record from flow builder to log_entry.
* Added `FlowLogEvent` at `src/Core/Framework/Event`.
* Added `LogAware` at `src/Core/Framework/Log`.
* Added `MailErrorEvent` at `src\Core\Content\MailTemplate\Service\Event`.
* Removed parameter `$logger` from method `Shopware\Core\Content\Mail\Service\MailService::__construct`
* Added implementation `LogAware` and remove implementation `LogAwareBusinessEventInterface` in these events:
  * `MailBeforeSentEvent`
  * `MailBeforeValidateEvent`
  * `MailSentEvent`
  * `ProductExportLoggingEvent`
___
# Next Major Version Changes
* Deprecated function `logBusinessEvent` at `src/Core/Framework/Log/LoggingService.php`.
* Deprecated `src/Core/Framework/Log/LogAwareBusinessEventInterface.php` use `LogAware` instead.
