[titleEn]: <>(Writer)

The `Writer` objects will get the converted data from the `swag_migration_data` table and write it to the right Shopware platform table.
Each `Writer` supports only one entity, that is most likely the target table.

When creating a writer, register it in a manner resembling the following:
```xml
<service id="SwagMigrationNext\Migration\Writer\ProductWriter">
    <argument type="service" id="Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter"/>
    <argument type="service" id="Shopware\Core\Content\Product\ProductDefinition"/>
    <tag name="shopware.migration.writer"/>
</service>
```

The writer class has to implement the `WriterInterface` and will receive the data in `writeData` method.
Received data is an array of converted values. The amount depends on the limit of the request.
Error handling is already done in the overlying `MigrationDataWriter` class. If the writing of all entries fails,
it will retry them one by one to minimize data loss.
```php
<?php declare(strict_types=1);

namespace SwagMigrationNext\Migration\Writer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use SwagMigrationNext\Migration\DataSelection\DefaultEntities;

class ProductWriter implements WriterInterface
{
    /**
     * @var EntityWriterInterface
     */
    private $entityWriter;

    /**
     * @var EntityDefinition
     */
    private $definition;

    public function __construct(EntityWriterInterface $entityWriter, EntityDefinition $definition)
    {
        $this->entityWriter = $entityWriter;
        $this->definition = $definition;
    }

    public function supports(): string
    {
        return DefaultEntities::PRODUCT;
    }

    /**
     * @param array[][] $data
     */
    public function writeData(array $data, Context $context): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($data) {
            $this->entityWriter->upsert(
                $this->definition,
                $data,
                WriteContext::createFromContext($context)
            );
        });
    }
}
```
