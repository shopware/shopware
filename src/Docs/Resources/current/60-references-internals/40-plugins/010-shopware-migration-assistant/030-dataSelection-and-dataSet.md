[titleEn]: <>(DataSelection and DataSet)
[hash]: <>(article:migration_data)

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
The `getEntityNamesRequiredForCount` method returns an array of all entities. Its count should be displayed in the administration.

`DataSelection` example:
```php
<?php declare(strict_types=1);

namespace SwagMigrationAssistant\Profile\Shopware\DataSelection;

use SwagMigrationAssistant\Migration\DataSelection\DataSelectionInterface;
use SwagMigrationAssistant\Migration\DataSelection\DataSelectionStruct;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\ManufacturerAttributeDataSet;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\MediaFolderDataSet;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\ProductAttributeDataSet;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\ProductDataSet;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\ProductPriceAttributeDataSet;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\PropertyGroupOptionDataSet;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\TranslationDataSet;
use SwagMigrationAssistant\Profile\Shopware\ShopwareProfileInterface;

class ProductDataSelection implements DataSelectionInterface
{
    public const IDENTIFIER = 'products';

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof ShopwareProfileInterface;
    }

    public function getData(): DataSelectionStruct
    {
        return new DataSelectionStruct(
            self::IDENTIFIER,
            $this->getEntityNames(),
            $this->getEntityNamesRequiredForCount(),
            'swag-migration.index.selectDataCard.dataSelection.products', // Snippet name
            100, // The position of the dataSelection
            true, // Is process-media needed (to download / copy images for example),
            DataSelectionStruct::BASIC_DATA_TYPE, // specify the type of data (core data or plugin data)
            true // Is the selection required for every migration? (the user can't unselect this data selection)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getEntityNames(): array
    {
        return [
            // The order matters!
            MediaFolderDataSet::getEntity(),
            ProductAttributeDataSet::getEntity(),
            ProductPriceAttributeDataSet::getEntity(),
            ManufacturerAttributeDataSet::getEntity(),
            ProductDataSet::getEntity(),
            PropertyGroupOptionDataSet::getEntity(),
            TranslationDataSet::getEntity(),
        ];
    }

    public function getEntityNamesRequiredForCount(): array
    {
        return [
            ProductDataSet::getEntity(),
        ];
    }
}
```

`DataSet` example:
The important part here is the `getCountingInformation` method, which provides the information to count the entity in the source system.

```php
<?php declare(strict_types=1);

namespace SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet;

use SwagMigrationAssistant\Migration\DataSelection\DataSet\CountingInformationStruct;
use SwagMigrationAssistant\Migration\DataSelection\DataSet\CountingQueryStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\ShopwareProfileInterface;

class ProductDataSet extends ShopwareDataSet
{
    public static function getEntity(): string
    {
        return DefaultEntities::PRODUCT;
    }

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof ShopwareProfileInterface;
    }

    public function getCountingInformation(): ?CountingInformationStruct
    {
        $information = new CountingInformationStruct(self::getEntity());
        $information->addQueryStruct(new CountingQueryStruct('s_articles_details')); // It is also possible to count a table using a condition
        // It is possible to add more Queries - the sum of the count from all queries will be stored for the entity

        return $information;
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
<service id="SwagMigrationAssistant\Profile\Shopware\DataSelection\ProductDataSelection">
    <tag name="shopware.migration.data_selection"/>
</service>
```

It is also possible to specify the same `DataSets` in multiple `DataSelections` (this should only be done if no other options are available).
Have a look at the `ProductReviewDataSelection`:
```php
<?php declare(strict_types=1);

namespace SwagMigrationAssistant\Profile\Shopware\DataSelection;

use SwagMigrationAssistant\Migration\DataSelection\DataSelectionInterface;
use SwagMigrationAssistant\Migration\DataSelection\DataSelectionStruct;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\CustomerAttributeDataSet;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\CustomerDataSet;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\ManufacturerAttributeDataSet;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\MediaFolderDataSet;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\ProductAttributeDataSet;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\ProductDataSet;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\ProductPriceAttributeDataSet;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\ProductReviewDataSet;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\PropertyGroupOptionDataSet;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\TranslationDataSet;
use SwagMigrationAssistant\Profile\Shopware\ShopwareProfileInterface;

class ProductReviewDataSelection implements DataSelectionInterface
{
    public const IDENTIFIER = 'productReviews';

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof ShopwareProfileInterface;
    }

    public function getData(): DataSelectionStruct
    {
        return new DataSelectionStruct(
            self::IDENTIFIER,
            $this->getEntityNames(),
            $this->getEntityNamesRequiredForCount(),
            'swag-migration.index.selectDataCard.dataSelection.productReviews',
            250,
            true
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getEntityNames(): array
    {
        return [
            MediaFolderDataSet::getEntity(),
            ProductAttributeDataSet::getEntity(),
            ProductPriceAttributeDataSet::getEntity(),
            ManufacturerAttributeDataSet::getEntity(),
            ProductDataSet::getEntity(),
            PropertyGroupOptionDataSet::getEntity(),
            TranslationDataSet::getEntity(),

            CustomerAttributeDataSet::getEntity(),
            CustomerDataSet::getEntity(),
            ProductReviewDataSet::getEntity(),
        ];
    }

    public function getEntityNamesRequiredForCount(): array
    {
        return [
            ProductReviewDataSet::getEntity(),
        ];
    }
}
```
There are duplicate DataSets from the `ProductDataSelection`, because they are also required if the user does not select the product `DataSelection`.
If the user selects both, this `DataSets` will be only migrated once (with their first occurrence).
