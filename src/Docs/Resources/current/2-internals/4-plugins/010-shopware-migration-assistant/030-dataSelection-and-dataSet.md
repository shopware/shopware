[titleEn]: <>(DataSelection and DataSet)

These are the fundamental data structures for define what to migrate.
Each `DataSelection` consists of one or more `DataSets`:
```text
- ProductDataSelection (position: 100)
    - MediaFolderDataSet
    - ProductAttributeDataSet
    - ProductPriceAttributeDataSet
    - ManufacturerAttributeDataSet
    - ProductDataSet
    - PropertyGroupOptionDataSet
    - TranslationDataSet
- MediaDataSelection (position: 300)
    - MediaFolderDataSet
    - MediaDataSet
```
The order of the `DataSets` in the `DataSelection` class is important and specifies the processing order.
`DataSelection` also holds a position specifying the order applied when migrating (lower numbers are migrated earlier).

`DataSelection` example:
```php
<?php declare(strict_types=1);

namespace SwagMigrationNext\Profile\Shopware55\DataSelection;

use SwagMigrationNext\Migration\DataSelection\DataSelectionInterface;
use SwagMigrationNext\Migration\DataSelection\DataSelectionStruct;
use SwagMigrationNext\Profile\Shopware55\DataSelection\DataSet\ManufacturerAttributeDataSet;
use SwagMigrationNext\Profile\Shopware55\DataSelection\DataSet\MediaFolderDataSet;
use SwagMigrationNext\Profile\Shopware55\DataSelection\DataSet\ProductAttributeDataSet;
use SwagMigrationNext\Profile\Shopware55\DataSelection\DataSet\ProductDataSet;
use SwagMigrationNext\Profile\Shopware55\DataSelection\DataSet\ProductPriceAttributeDataSet;
use SwagMigrationNext\Profile\Shopware55\DataSelection\DataSet\PropertyGroupOptionDataSet;
use SwagMigrationNext\Profile\Shopware55\DataSelection\DataSet\TranslationDataSet;
use SwagMigrationNext\Profile\Shopware55\Shopware55Profile;

class ProductDataSelection implements DataSelectionInterface
{
    public const IDENTIFIER = 'products';

    public function supports(string $profileName, string $gatewayIdentifier): bool
    {
        return $profileName === Shopware55Profile::PROFILE_NAME;
    }

    public function getData(): DataSelectionStruct
    {
        return new DataSelectionStruct(
            self::IDENTIFIER,
            $this->getEntityNames(),
            'swag-migration.index.selectDataCard.dataSelection.products', // Snippet name
            100, // The position of the dataSelection
            true // Is process-media needed (to download / copy images for example)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getEntityNames(): array
    {
        return [
            // The order matters
            MediaFolderDataSet::getEntity(),
            ProductAttributeDataSet::getEntity(),
            ProductPriceAttributeDataSet::getEntity(),
            ManufacturerAttributeDataSet::getEntity(),
            ProductDataSet::getEntity(),
            PropertyGroupOptionDataSet::getEntity(),
            TranslationDataSet::getEntity(),
        ];
    }
}
```

`DataSet` example:
```php
<?php declare(strict_types=1);

namespace SwagMigrationNext\Profile\Shopware55\DataSelection\DataSet;

use SwagMigrationNext\Migration\DataSelection\DefaultEntities;
use SwagMigrationNext\Profile\Shopware55\Shopware55Profile;

class ProductDataSet extends Shopware55DataSet
{
    public static function getEntity(): string
    {
        return DefaultEntities::PRODUCT;
    }

    public function supports(string $profileName, string $entity): bool
    {
        return $profileName === Shopware55Profile::PROFILE_NAME && $entity === self::getEntity();
    }

    public function getApiRoute(): string
    {
        return 'SwagMigrationProducts';
    }

    public function getExtraQueryParameters(): array
    {
        return [];
    }
}
```

The `dataSelections` are registered the following way:
```xml
<service id="SwagMigrationNext\Profile\Shopware55\DataSelection\ProductDataSelection">
    <tag name="shopware.migration.data_selection"/>
</service>
```

