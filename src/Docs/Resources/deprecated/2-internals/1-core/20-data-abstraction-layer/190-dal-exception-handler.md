[titleEn]: <>(DAL Exception Handler)
[hash]: <>(article:dal_exception_handler)

The `ExceptionHandler` is a way to catch defined Database exceptions while perform `writeCommands`

#### `ExceptionHandlerRegistry`
The `ExceptionHandlerRegistry` holds every registered `ExceptionHandler` and is responsible to match any given Database exception
with a custom exception with a more detailed code and message.

## Add a custom ExceptionHandler

To add a custom ExceptionHandler you need to write one which implements the `ExcpetionHandlerInterface` and provide a
matching condition to the method `matchException`. As an example you can take the `ProductExceptionHandler`

```
class ProductExceptionHandler implements ExceptionHandlerInterface
{
    public function matchException(\Exception $e, WriteCommand $command): ?\Exception
    {
        if ($e->getCode() !== 0 || $command->getDefinition()->getEntityName() !== 'product') {
            return null;
        }

        if (preg_match('/SQLSTATE\[23000\]:.*1062 Duplicate.*\'uniq.product.product_number__version_id\'/', $e->getMessage())) {
            $payload = $command->getPayload();

            return new DuplicateProductNumberException($payload['product_number'] ?? '');
        }

        return null;
    }
}
```

Be sure your `ExceptionHandler` only matches for the case you want it to. If the Exception is only responsible for a single
entity, check if you are in the right entity like above. Be sure the preg_match only matches your key and nothing else.

Your new `ExceptionHandler` must be registered as a tagged service with the tag `shopware.dal.exception_handler`.
