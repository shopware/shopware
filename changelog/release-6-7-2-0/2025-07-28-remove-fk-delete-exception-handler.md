---
title: Remove FK delete exception handler
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityForeignKeyResolver::fetch` to return language foreign keys for restrict delete and thus enabling foreign key checks in `\Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter::extractDeleteCommands`.
* Changed all implementations of `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface`, that check for foreign key violations to not throw any exceptions anymore, as the DAL now handles those directly. This means that the following handler classes and excpetions are now deprecated:
  * `LanguageOfOrderDeleteException`
  * `OrderExceptionHandler`
  * `LanguageOfNewsletterDeleteException`
  * `NewsletterExceptionHandler`
  * `LanguageForeignKeyDeleteException`
  * `LanguageExceptionHandler`
  * `SalesChannelException::salesChannelDomainInUse`
  * `SalesChannelExceptionHandler`
  * `ThemeExceptionHandler`
  * `ThemeException::themeMediaStillInUse`
___
# Upgrade Information
## Deprecate FK delete exception handler
All foreign keys with restrict delete behavior are now handled directly by the DAL.
This means that the following exceptions are not thrown anymore:
* `LanguageOfOrderDeleteException`
* `LanguageOfNewsletterDeleteException`
* `LanguageForeignKeyDeleteException`
* `ThemeException::themeMediaStillInUse`
* `SalesChannelException::salesChannelDomainInUse`
In the cases that previously those exceptions were thrown, now a `RestrictDeleteViolationException` is thrown as in all other cases.

Additionally, the following exception handlers don't throw any exceptions anymore and are deprecated and will be removed in v6.8.0.0:
* `OrderExceptionHandler`
* `NewsletterExceptionHandler`
* `LanguageExceptionHandler`
* `SalesChannelExceptionHandler`
* `ThemeExceptionHandler`
___
# Next Major Version Changes
## Remove FK delete exception handler
All foreign key checks are now handled directly by the DAL, therefore the following exception handler did not any effect anymore and are removed:
* `OrderExceptionHandler`
* `NewsletterExceptionHandler`
* `LanguageExceptionHandler`
* `SalesChannelExceptionHandler`
* `ThemeExceptionHandler`
This also means that the following exceptions are not thrown anymore and were removed as well:
* `LanguageOfOrderDeleteException`
* `LanguageOfNewsletterDeleteException`
* `LanguageForeignKeyDeleteException`
* `ThemeException::themeMediaStillInUse`
* `SalesChannelException::salesChannelDomainInUse`