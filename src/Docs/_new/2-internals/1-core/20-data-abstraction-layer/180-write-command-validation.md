[titleEn]: <>(Write Command Validation)

The `WriteCommand` validation is used to validate data at the lowest level. It runs just before and after the data has been written to the database.

You need to implement the `\Shopware\Core\Framework\Validation\WriteCommandValidatorInterface` and tag it with `shopware.validate` to register it in the DI container.

There are two methods you can implement:

1. `WriteCommandValidatorInterface::preValidate`: is called pre write. One use case is to catch invalid deletes.
2. `WriteCommandValidatorInterface::postValidate`: is called after the `WriteCommand`s are executed, but before the transaction is committed. You can check new data in combination with existing data.

Validators must throw a `\Shopware\Core\Framework\Validation\ConstraintViolationException`, to signal a constraint violation. Any thrown exception aborts and rollbacks the transaction.

You can take a look at `\Shopware\Core\System\Language\LanguageValidator`
as an example.

There are a few things to watch for. `WriteCommand`s use the `storageName` and not the `propertyName`. So it's `language_id` instead of `languageId`. Currently you cannot use the repositories or the `EntitySearcher` in the validate functions, until it's possible to disable the caching during the validation.