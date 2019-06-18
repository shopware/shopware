[titleEn]: <>(Write validation refactoring)

The write validation has grown big since the beginning of the DAL. Therefore, it has been cleaned up and here are the changes:

* The trait `FieldValidatorTrait` has been replaced by the abstract class `AbstractFieldSerializer` which implements all of it's methods
* The `AbstractFieldSerializer` has a new method `validateIfNeeded()` as a shorthand to `requiresValidation()` + `validate()`
* FieldSerializers can overwrite the `getConstraints()` method to use a standardized way to validate the data
* FieldSerializers should throw exceptions of type `WriteConstraintViolationException`.
* The following exceptions have been removed in favour of the `WriteException`:
  * `FieldExceptionStack`
  * `InsufficientWritePermissionException`
  * `InvalidFieldException`
  * `InvalidJsonFieldException`
* The `WriteException` is the only exception which will be thrown in case something went wrong during the write. It contains all thrown exceptions.
* The `WriteParameterBag` no longer contains an exception stack, it has been moved to the `WriteContext`.
* The `CommandQueueValidator` has been removed as we now use events for Pre/Post write validation.
  * `Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent`: is called pre write. One use case is to catch invalid deletes.
  * `Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent`: is called after the `WriteCommand`s are executed, but before the transaction is committed. You can check new data in combination with existing data.
  * Validators must add an exception to the event's context found at `$event->getExceptions()` to signal a constraint violation. Any added exception aborts and rollbacks the transaction.


