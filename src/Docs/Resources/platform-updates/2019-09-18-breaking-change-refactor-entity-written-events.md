[titleEn]: <>(Refactoring of EntityWritten[Container]Events and EntityWriteResults)

We made breaking changes to almost all events concerning the entity write process to make the `EntityWrittenContainerEvent` serializable. 
This allows us to move some of the larger, less important tasks which can be triggered by entity changes (like reindexing of seo urls) to the 
message queue and handle them asynchronously. 


We removed all `EntityDefinition`s from `EntityWrittenEvent`s, `EntityDeletedEvent`s  and from classes used 
as members in these events ( namely `EntityWriteResult` and `EntityExistence`). These classes now only contain the
`entityName` of the affected entities. If you need the definitions you have to retrieve them using the `DefinitionInstanceRegistry`
when handling these events. 

This change required further changes to the `EntityWrittenEvent`: 
* The `getWrittenDefinitions` method was removed, as the event cannot provide these definitions anymore. 
* Removed the `getEventByDefinition` method and replaced it with `getEventByEntityName`. This new function requires the 
name of an entity instead of the qualified classname. 

The following RegEx can be used with PHPStorms search-and-replace function (remember to turn on regex support)
to fix static usages of this function if they are not typehinted correctly:

Search Regex : `\-\>getEventByDefinition\(([a-zA-Z0-9]+)\:\:class\)`
Replace Regex: `\-\>getEventByEntityName\($1\:\:ENTITY_NAME\)`

We also changed the contents of `EntityExistence::primaryKey` to always contain the entity ids in a hexadecimal
format instead of binary, as binary blobs cannot be serialized with `json_encode`.

The removal of the `EntityDefinition`s from the `EntityWrittenEvent` also required us to add an additional parameter
to the constructor of `Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\AbstractFieldSerializer`. Subclasses 
must now provide a `DefinitonInstanceRegistry` when calling the superconstructor. As the registry now resides in the superclass,
the following registries could be removed from classes extending `AbstractFieldSerializer`:
* `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer::fieldHandlerRegistry`
* `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceFieldSerializer::fieldHandlerRegistry` 
* `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ListFieldSerializer::compositeHandler` 
