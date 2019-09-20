[titleEn]: <>(Converter and mapping)

Data gathered by `Reader` objects is transferred to `Converter` objects that put the data in a format Shopware 6 is able to work with.
Simultaneously entries in the underlying mapping table are inserted to map the old identifiers to the new ones for future migrations.
The mapping is saved for the current connection. Converted data will be removed after the migration, the mapping will stay persistent.

## Converter
All converters are registered in service container like this:
```xml
<service id="SwagMigrationNext\Profile\Shopware55\Converter\ProductConverter">
    <argument type="service" id="SwagMigrationNext\Migration\Mapping\MappingService"/>
    <argument type="service" id="SwagMigrationNext\Migration\Media\MediaFileService"/>
    <argument type="service" id="SwagMigrationNext\Migration\Logging\LoggingService"/>
    <tag name="shopware.migration.converter"/>
</service>
```
The converters have to extend the `AbstractConverter` class and implement the `convert` method.
This method will receive one data entry at a time. It will have to return it in the right format in order to be usable for the `writer`.

```php
<?php declare(strict_types=1);

class ProductConverter extends ShopwareConverter
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
        $this->context = $context;
        $this->runId = $migrationContext->getRunUuid();
        $this->connectionId = $migrationContext->getConnection()->getId();
        $this->oldProductId = $data['detail']['ordernumber'];
        $this->mainProductId = $data['detail']['articleID'];
        $this->locale = $data['_locale'];

        $fields = $this->checkForEmptyRequiredDataFields($data, $this->requiredDataFieldKeys);
        if (!empty($fields)) {
            foreach ($fields as $field) {
                $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                    $this->runId,
                    DefaultEntities::PRODUCT,
                    $this->oldProductId,
                    $field
                ));
            }

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

        if (empty($data)) {
            $data = null;
        }

        return new ConvertStruct($converted, $data);
    }
    
    /* ... */
}
```
As you see above the `convert` method gets the source system data, checks with `checkForEmptyRequiredDataFields` if the
necessary data fields are filled and returns a `ConvertStruct`. The `ConvertStruct` contains the converted value in the structure
of Shopware 6 and all source system data which could not be mapped to the Shopware 6 structure.
If the required fields are not filled, the convert method returns a `ConvertStruct` without a `converted` value and the hole given
source system data as the `unmapped` value.

## Mapping

Many entities rely on other entities, so that they have to be converted in a specific order. Because of this and the
Shopware Migration Assistant's ability to perform multiple migrations without resetting Shopware 6 itself,
source system identifiers have to be mapped to their new counterparts.
Find a mapping example in the following code snippet:
```php
    private function getUuidForProduct(array &$data): array
    {
        $converted['id'] = $this->mappingService->createNewUuid(
            $this->connectionId,
            DefaultEntities::PRODUCT,
            $this->oldProductId,
            $this->context
        );

        $this->mappingService->createNewUuid(
            $this->connectionId,
            DefaultEntities::PRODUCT . '_mainProduct',
            $data['detail']['articleID'],
            $this->context,
            null,
            $converted['id']
        );

        return $converted;
    }
```
The following function employs the `createNewUuid` function, that is part of the mapping service to acquire a unique identifier
for the product that is about to get mapped to the source system's identifier and at the same time creating a new mapping
entry in the `swag_migration_mapping` table. In case of that there is already a unique identifier for the product,
the `createNewUuid` instead of creating a duplicate entry, returns the existing identifier:
```php
public function createNewUuid(
        string $connectionId,
        string $entityName,
        string $oldId,
        Context $context,
        ?array $additionalData = null,
        ?string $newUuid = null
    ): string {
        $uuid = $this->getUuid($connectionId, $entityName, $oldId, $context);
        if ($uuid !== null) {
            return $uuid;
        }

        $uuid = Uuid::randomHex();
        if ($newUuid !== null) {
            $uuid = $newUuid;
        }

        $this->saveMapping(
            [
                'connectionId' => $connectionId,
                'entity' => $entityName,
                'oldIdentifier' => $oldId,
                'entityUuid' => $uuid,
                'additionalData' => $additionalData,
            ]
        );

        return $uuid;
    }
```

Sometimes it is not necessary to create a new identifier and it may be enough to only get the mapping identifier.
In the following example there is an entity with a premapping and the converter simply uses the mapping service's `getUuid` method.:
```php
private function getDefaultPaymentMethod(array $originalData): ?string
{
    $paymentMethodUuid = $this->mappingService->getUuid(
        $this->connectionId,
        PaymentMethodReader::getMappingName(),
        $originalData['id'],
        $this->context
    );

    if ($paymentMethodUuid === null) {
        $this->loggingService->addWarning(
            $this->runId,
            Shopware55LogTypes::UNKNOWN_PAYMENT_METHOD,
            'Cannot find payment method',
            'Customer-Entity could not be converted cause of unknown payment method',
            [
                'id' => $this->oldCustomerId,
                'entity' => DefaultEntities::CUSTOMER,
                'paymentMethod' => $originalData['id'],
            ]
        );
    }

    return $paymentMethodUuid;
}
```
The `getUuid` method only fetches the identifier from the database and doesn't create a new one:
```php
public function getUuid(string $connectionId, string $entityName, string $oldId, Context $context): ?string
{
    if (isset($this->uuids[$entityName][$oldId])) {
        return $this->uuids[$entityName][$oldId];
    }

    /** @var EntitySearchResult $result */
    $result = $context->disableCache(function (Context $context) use ($connectionId, $entityName, $oldId) {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('connectionId', $connectionId));
        $criteria->addFilter(new EqualsFilter('entity', $entityName));
        $criteria->addFilter(new EqualsFilter('oldIdentifier', $oldId));
        $criteria->setLimit(1);

        return $this->migrationMappingRepo->search($criteria, $context);
    });

    if ($result->getTotal() > 0) {
        /** @var SwagMigrationMappingEntity $element */
        $element = $result->getEntities()->first();
        $uuid = $element->getEntityUuid();

        $this->uuids[$entityName][$oldId] = $uuid;

        return $uuid;
    }

    return null;
}
```
