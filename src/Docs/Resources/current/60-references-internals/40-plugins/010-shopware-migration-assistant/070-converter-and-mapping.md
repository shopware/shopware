[titleEn]: <>(Converter and mapping)
[hash]: <>(article:migration_converter)

Data gathered by `Reader` objects is transferred to `Converter` objects that put the data in a format Shopware 6 is able to work with.
Simultaneously entries in the underlying mapping table are inserted to map the old identifiers to the new ones for future migrations.
The mapping is saved for the current connection. Converted data will be removed after the migration, the mapping will stay persistent.

## Converter
All converters are registered in service container like this:
```xml
<service id="SwagMigrationAssistant\Profile\Shopware\Converter\ProductConverter"
         parent="SwagMigrationAssistant\Profile\Shopware\Converter\ShopwareConverter" abstract="true">
    <argument type="service" id="SwagMigrationAssistant\Migration\Media\MediaFileService"/>
</service>
```
The converters have to extend the `ShopwareConverter` class and implement the `convert` method.
This method will receive one data entry at a time. It will have to return it in the right format in order to be usable for the `writer`.

```php
<?php declare(strict_types=1);

/* SwagMigrationAssistant/Profile/Shopware/Converter/ProductConverter.php */

abstract class ProductConverter extends ShopwareConverter
{
    /* ... */

    /**
     * @throws ParentEntityForChildNotFoundException
     */
    public function convert(
        array $data,
        Context $context,
        MigrationContextInterface $migrationContext
    ): ConvertStruct {
        $this->generateChecksum($data);
        $this->context = $context;
        $this->migrationContext = $migrationContext;
        $this->runId = $migrationContext->getRunUuid();
        $this->oldProductId = $data['detail']['ordernumber'];
        $this->mainProductId = $data['detail']['articleID'];
        $this->locale = $data['_locale'];

        $connection = $migrationContext->getConnection();
        $this->connectionName = '';
        $this->connectionId = '';
        if ($connection !== null) {
            $this->connectionId = $connection->getId();
            $this->connectionName = $connection->getName();
        }

        $fields = $this->checkForEmptyRequiredDataFields($data, $this->requiredDataFieldKeys);
        if (!empty($fields)) {
            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $this->runId,
                DefaultEntities::PRODUCT,
                $this->oldProductId,
                implode(',', $fields)
            ));

            return new ConvertStruct(null, $data);
        }

        $this->productType = (int) $data['detail']['kind'];
        unset($data['detail']['kind']);
        $isProductWithVariant = $data['configurator_set_id'] !== null;

        if ($this->productType === self::MAIN_PRODUCT_TYPE && $isProductWithVariant) {
            return $this->convertMainProduct($data);
        }

        if ($this->productType === self::VARIANT_PRODUCT_TYPE && $isProductWithVariant) {
            return $this->convertVariantProduct($data);
        }

        $converted = $this->getUuidForProduct($data);
        $converted = $this->getProductData($data, $converted);

        if (isset($data['categories'])) {
            $converted['categories'] = $this->getCategoryMapping($data['categories']);
        }
        unset($data['categories']);

        if (isset($data['shops'])) {
            $converted['visibilities'] = $this->getVisibilities($converted, $data['shops']);
        }
        unset($data['shops']);

        unset($data['detail']['id'], $data['detail']['articleID']);

        if (empty($data['detail'])) {
            unset($data['detail']);
        }

        $returnData = $data;
        if (empty($returnData)) {
            $returnData = null;
        }
        $this->updateMainMapping($migrationContext, $context);

        $mainMapping = $this->mainMapping['id'] ?? null;

        return new ConvertStruct($converted, $returnData, $mainMapping);
    }
    
    /* ... */
}
```
As you see above the `convert` method gets the source system data, checks with `checkForEmptyRequiredDataFields` if the
necessary data fields are filled and returns a `ConvertStruct`. The `ConvertStruct` contains the converted value in the structure
of Shopware 6 and all source system data which could not be mapped to the Shopware 6 structure.
If the required fields are not filled, the convert method returns a `ConvertStruct` without a `converted` value and all of the given
source system data as the `unmapped` value.

Also every `Converter` needs to implement the `getSourceIdentifier` method like below:
```php
/* SwagMigrationAssistant/Profile/Shopware/Converter/ProductConverter.php */

/**
 * Get the identifier of the source data which is only known to converter
 */
public function getSourceIdentifier(array $data): string
{
    return $data['detail']['ordernumber'];
}
```
This is the main identifier of the incoming data and it will be used to look for already migrated data
(which will be covered later in this chapter by the Deltas concept).

## Mapping

Many entities rely on other entities, so that they have to be converted in a specific order. Because of this and the
Shopware Migration Assistant's ability to perform multiple migrations without resetting Shopware 6 itself,
source system identifiers have to be mapped to their new counterparts.
Find a mapping example in the following code snippet:
```php
    /* SwagMigrationAssistant/Profile/Shopware/Converter/ProductConverter.php */
    
    private function getUuidForProduct(array &$data): array
    {
        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::PRODUCT,
            $this->oldProductId,
            $this->context,
            $this->checksum
        );

        $converted = [];
        $converted['id'] = $this->mainMapping['entityUuid'];

        $mapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::PRODUCT_MAIN,
            $data['detail']['articleID'],
            $this->context,
            null,
            null,
            $converted['id']
        );
        $this->mappingIds[] = $mapping['id']; // Take a look at the performance section below for details on this.

        return $converted;
    }
```
The following function employs the `getOrCreateMapping` function, that is part of the mapping service to acquire a unique identifier
for the product that is about to get mapped to the source system's identifier and at the same time creating a new mapping
entry in the `swag_migration_mapping` table. If there already is a unique identifier for the product,
the `getOrCreateMapping` method instead of creating a duplicate entry, returns the existing identifier:
```php
    /* SwagMigrationAssistant/Migration/Mapping/MappingService.php */
    
    public function getOrCreateMapping(
        string $connectionId,
        string $entityName,
        string $oldIdentifier,
        Context $context,
        ?string $checksum = null,
        ?array $additionalData = null,
        ?string $uuid = null
    ): array {
        $mapping = $this->getMapping($connectionId, $entityName, $oldIdentifier, $context);

        if (!isset($mapping)) {
            return $this->createMapping($connectionId, $entityName, $oldIdentifier, $checksum, $additionalData, $uuid);
        }

        if ($uuid !== null) {
            $mapping['entityUuid'] = $uuid;
            $this->saveMapping($mapping);

            return $mapping;
        }

        return $mapping;
    }
```

Sometimes it is not necessary to create a new identifier and it may be enough to only get the mapping identifier.
In the following example there is an entity with a premapping and the converter simply uses the mapping service's `getMapping` method:
```php
/* SwagMigrationAssistant/Profile/Shopware/Converter/CustomerConverter.php */

protected function getDefaultPaymentMethod(array $originalData): ?string
{
    $paymentMethodMapping = $this->mappingService->getMapping(
        $this->connectionId,
        PaymentMethodReader::getMappingName(),
        $originalData['id'],
        $this->context
    );

    if ($paymentMethodMapping === null) {
        $this->loggingService->addLogEntry(new UnknownEntityLog(
            $this->runId,
            DefaultEntities::PAYMENT_METHOD,
            $originalData['id'],
            DefaultEntities::CUSTOMER,
            $this->oldCustomerId
        ));

        return null;
    }
    $this->mappingIds[] = $paymentMethodMapping['id'];

    return $paymentMethodMapping['entityUuid'];
}
```
The `getMapping` method only fetches the identifier from the database and doesn't create a new one:
```php
/* SwagMigrationAssistant/Migration/Mapping/MappingService.php */

public function getMapping(
    string $connectionId,
    string $entityName,
    string $oldIdentifier,
    Context $context
): ?array {
    if (isset($this->mappings[md5($entityName . $oldIdentifier)])) {
        return $this->mappings[md5($entityName . $oldIdentifier)];
    }
    /** @var EntitySearchResult $result */
    $result = $context->disableCache(function (Context $context) use ($connectionId, $entityName, $oldIdentifier) {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('connectionId', $connectionId));
        $criteria->addFilter(new EqualsFilter('entity', $entityName));
        $criteria->addFilter(new EqualsFilter('oldIdentifier', $oldIdentifier));
        $criteria->setLimit(1);

        return $this->migrationMappingRepo->search($criteria, $context);
    });

    if ($result->getTotal() > 0) {
        /** @var SwagMigrationMappingEntity $element */
        $element = $result->getEntities()->first();

        $mapping = [
            'id' => $element->getId(),
            'connectionId' => $element->getConnectionId(),
            'entity' => $element->getEntity(),
            'oldIdentifier' => $element->getOldIdentifier(),
            'entityUuid' => $element->getEntityUuid(),
            'checksum' => $element->getChecksum(),
            'additionalData' => $element->getAdditionalData(),
        ];
        $this->mappings[md5($entityName . $oldIdentifier)] = $mapping;

        return $mapping;
    }

    return null;
}
```

## Deltas

One of the parameters for the `getOrCreateMapping` Method is the `checksum`.
It is used to identify unchanged data (source system data that has not been changed since the last migration).
This will greatly improve the performance of future migrations.

To get this checksum you can use the `generateChecksum` Method of the base `Converter` class:
```php
/* SwagMigrationAssistant/Migration/Converter/Converter.php */

/**
 * Generates a unique checksum for the data array to recognize changes
 * on repeated migrations.
 */
protected function generateChecksum(array $data): void
{
    $this->checksum = md5(serialize($data));
}
```

This is used in the first line of the converter with the raw data that comes from the `Reader` object:
```php
/* SwagMigrationAssistant/Profile/Shopware/Converter/ProductConverter.php */

public function convert(
    array $data,
    Context $context,
    MigrationContextInterface $migrationContext
): ConvertStruct {
    $this->generateChecksum($data);

    /* ... */

    // This is also important, so the checksum can be saved to the right mapping!
    $this->mainMapping = $this->mappingService->getOrCreateMapping(
        $this->connectionId,
        DefaultEntities::PRODUCT,
        $this->oldProductId,
        $this->context,
        $this->checksum
    );

    /* ... */
    
    // Important to put the mainMapping['id'] to the ConvertStruct
    $mainMapping = $this->mainMapping['id'] ?? null;
    return new ConvertStruct($converted, $returnData, $mainMapping);
    
    /* ... */
}
```

For the checksum to be saved to the right mapping, make sure that you set the `mainMapping` attribute of the base `Converter` class.
Internally the checksum of the main mapping of an entity will be compared to the incoming data checksum and if it is the same
it will be skipped by the converter and also by the writer (you will not receive the data with the same checksum in your converter), 
which increases performance of repeated migrations massively.
For more information take a look at the corresponding `filterDeltas` method in the `MigrationDataConverter` class.
Important for the delta concept is to return the `mainMapping` with the `ConvertStruct`, this is necessary to map the converted
data to the main mapping entry.

## Additional performance tips

The `Converter` base class also contains an array named `mappingIds`.
This can be filled with all mapping IDs that relate to the current data.
Internally the related mappings will be fetched all at once in future migrations,
which reduces the performance impact of `getMapping` calls
(because not every call needs to query data from the database).
So it is advised to add related mapping IDs in the following manner:
```php
/* SwagMigrationAssistant/Profile/Shopware/Converter/ProductConverter.php */

private function getUnit(array $data): array
{
    $unit = [];
    $mapping = $this->mappingService->getOrCreateMapping(
        $this->connectionId,
        DefaultEntities::UNIT,
        $data['id'],
        $this->context
    );
    $unit['id'] = $mapping['entityUuid'];
    $this->mappingIds[] = $mapping['id']; // Store the mapping id as related mapping

    $this->getUnitTranslation($unit, $data);
    $this->convertValue($unit, 'shortCode', $data, 'unit');
    $this->convertValue($unit, 'name', $data, 'description');

    return $unit;
}
```
To save these mapping IDs in the `mainMapping`, it is necessary to call the `updateMainMapping` before returning the `ConvertStruct`:
```php
/* SwagMigrationAssistant/Profile/Shopware/Converter/ProductConverter.php */

public function convert(
    array $data,
    Context $context,
    MigrationContextInterface $migrationContext
): ConvertStruct {
    /* ... */
    
    $this->updateMainMapping($this->migrationContext, $this->context);
    
    $mainMapping = $this->mainMapping['id'] ?? null;
    
    return new ConvertStruct($converted, $returnData, $mainMapping);
    
    /* ... */
}
```
