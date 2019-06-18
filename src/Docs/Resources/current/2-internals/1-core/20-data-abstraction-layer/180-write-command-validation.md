[titleEn]: <>(Write Command Validation)

The `WriteCommand` validation is used to validate data at the lowest level. It runs just before and after the data has been written to the database.

There are two events you can subscribe to:

1. `Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent`: is called pre write. One use case is to catch invalid deletes.
2. `Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent`: is called after the `WriteCommand`s are executed, but before the transaction is committed. You can check new data in combination with existing data.

Validators must add an exception to the event's context found at `$event->getExceptions()` to signal a constraint violation. Any added exception aborts and rollbacks the transaction.

You can take a look at `\Shopware\Core\Framework\Language\LanguageValidator` as an example.

There are a few things to watch for. `WriteCommand`s use the `storageName` and not the `propertyName`. So it's `language_id` instead of `languageId`. Currently you cannot use the repositories or the `EntitySearcher` in the validate functions, until it's possible to disable the caching during the validation.
