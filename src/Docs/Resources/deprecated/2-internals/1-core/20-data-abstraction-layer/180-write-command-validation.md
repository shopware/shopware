[titleEn]: <>(Write Command Validation)
[hash]: <>(article:dal_write_command_validation)

The `WriteCommand` validation is used to validate data at the lowest level. It runs just before and after the data has been written to the database.

There are two events you can subscribe to:

1. `Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent`: is called pre write. One use case is to catch invalid deletes.
2. `Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent`: is called after the `WriteCommand`s are executed, but before the transaction is committed. You can check new data in combination with existing data.

Validators must add an exception to the event's context found at `$event->getExceptions()` to signal a constraint violation. Any added exception aborts and rollbacks the transaction.

You can take a look at `\Shopware\Core\Content\Rule\RuleValidator` as an example.

There are a few things to watch for. `WriteCommand`s use the `storageName` and not the `propertyName`. So it's `language_id` instead of `languageId`. Currently you cannot use the repositories or the `EntitySearcher` in the validate functions, until it's possible to disable the caching during the validation.

## Use the PreWriteValidationEvent for validation.

As described before, the `PreWriteValidationEvent` should be used to validate the requested write commands before they are written to the database. To register a validator your service has to implement the `EventSubscriberInterface` and must be tagged as `kernel.event.subscriber` in your `service.xml`. Then you can simply subscribe to the event like you would do for any other Symfony event:

```
public static function getSubscribedEvents(): array
{
    return [
        \Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent::class => 'preValidate',
    ];
}
```

A `PreWriteValidationEvent` will be passed to your subscriber which gives you access to the `WriteContext` and `WriteCommandQueue`.

### Use WriteConstraintViolationExceptions to signal validation errors

If validation against a `WriteCommand` fails you must not throw an exception directly in order to not interrupt further validation. You rather want to collect violations and send them back to the client application at once.

To collect constraint violations create a `ContraintViolationList` and add `ConstraintViolation` objects for each invalid property of your `WriteCommands` payload. If a constraint violation had occur wrap the `ContraintViolationList` in a `ConstraintViolationException` and add it to the `context`'s global exception by calling `$event->getExceptions()->add(yourConstraintViolationException)`.

Keep in mind that at this point of the write operation, the `WriteCommandQueue` is already in write order. That means that nested associations in the request are extracted and order may vary from request's payload to resolve dependencies between entities. 
 
For example: If you create a new `Rule` and directly assign `RuleConditions` to it your update data may look like something like this:

``` 
{
  "name": "new rule",
  "priority": 10
  "conditions": [
    {
      "type": "orContainer",
      "position": 1
      "children": [
        {
          "type": "cartWeight",
          "position": 1,
          "value": {
            "operator": ">",
            "weight": 1000
          }
        }, {
          "type": "invalidType"
          "position": 2,
          "value": {
            "operator": ">",
            "amount": 'invalid'
          }
       }
    }
  ]
}
```  

The command queue however is a flat list of `InsertCommands`, one for the rule itself and one for each of the `RuleConditions`. Since the client application does only know its request you must map back the `WriteCommand` its original position in the request. this can be easily done by calling the `getPath` method of your `WriteCommand` and pass it as the `$path` constructor argument when creating your `ConstraintViolationException`. For example: the path to the invalid `RuleCondition` above would be `/0/conditions/0/childrens/1` since it is the 2nd child of the first condition for the first rule to write.
