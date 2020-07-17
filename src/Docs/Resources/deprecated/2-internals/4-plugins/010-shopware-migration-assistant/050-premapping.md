[titleEn]: <>(Premapping)
[hash]: <>(article:migration_premapping)

The premapping will use the normal [Mapping](./070-converter-and-mapping.md) to store the old identifier with the equivalent new one.
All premapping readers provide the information for the mapping choices and are registered like this:
```xml
<service id="SwagMigrationAssistant\Profile\Shopware\Premapping\SalutationReader">
    <argument type="service" id="salutation.repository" />
    <argument type="service" id="SwagMigrationAssistant\Migration\Gateway\GatewayRegistry"/>
    <tag name="shopware.migration.pre_mapping_reader"/>
</service>
```
The service will return a `PremappingStruct`, which consists of:
1. entity of the premapping
2. choices, representing Shopware 6 equivalents
3. mapping, representing the source system's structure including a destination / choice

Here is an example of how the final `PremappingStruct` looks like in the `generate-premapping` json response:
```json
{
   "entity":"salutation",
   "choices":[
      {
         "uuid":"d4883ea9db2b4a5ca033873903358062",
         "description":"mr",
         "extensions":[

         ]
      },
      {
         "uuid":"7a7ef1e4a9064c46b5f85e28b4d942a9",
         "description":"mrs",
         "extensions":[

         ]
      },
      {
         "uuid":"a6fa00aef9a648d9bd012dbe16c112bf",
         "description":"not_specified",
         "extensions":[

         ]
      }
   ],
   "mapping":[
      {
         "sourceId":"mr",
         "description":"mr",
         "destinationUuid":"d4883ea9db2b4a5ca033873903358062",
         "extensions":[

         ]
      },
      {
         "sourceId":"ms",
         "description":"ms",
         "destinationUuid":"",
         "extensions":[

         ]
      }
   ]
}
```
The `destinationUuid` in the `mapping` array sets the destination for that entity.
It will be saved along with the [Connection](./020-profile-and-connection.md), so the user does not have to make these decisions
over and over again. For more details of how the mapping process works and even more information on automatic assignment,
look up more on the `SalutationReader` class.

To get the associated new identifier, you can make use of the `MappingService` similar to the `CustomerConverter`:
```php
<?php declare(strict_types=1);

/* ... */

protected function getSalutation(string $salutation): ?string
{
    $mapping = $this->mappingService->getMapping(
        $this->connectionId,
        SalutationReader::getMappingName(),
        $salutation,
        $this->context
    );

    if ($mapping === null) {
        $this->loggingService->addLogEntry(new UnknownEntityLog(
            $this->runId,
            DefaultEntities::SALUTATION,
            $salutation,
            DefaultEntities::CUSTOMER,
            $this->oldCustomerId
        ));

        return null;
    }
    $this->mappingIds[] = $mapping['id'];

    return $mapping['entityUuid'];
}

/* ... */
```
The `getMapping` method used in the mapping service looks up the `swag_migration_mapping` table for the combination of
old identifier and entity name stored in the current connection.
Then it returns the mapping object, which contains the new Shopware 6 identifier.
With this identifier it is possible to map your converted entity to your premapping choice. If `getMapping` returns null,
then no valid mapping is available and you have to log this with [LoggingService](./071-logging.md). The mapping object has
two keys: `id` and `entityUuid`. The `id` key is the identifier of the `swag_migration_mapping` entry
and have to be inserted in the `mappingIds`, if the mapping should be preloaded. The `entityUuid` key is the UUID of the mapped entity.
