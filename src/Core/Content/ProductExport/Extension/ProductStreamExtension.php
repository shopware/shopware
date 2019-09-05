<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Extension;

use Shopware\Core\Content\ProductExport\ProductExportDefinition;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductStreamExtension implements EntityExtensionInterface
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField('productExports', ProductExportDefinition::class, 'product_stream_id', 'id')
        );
    }

    public function getDefinitionClass(): string
    {
        return ProductStreamDefinition::class;
    }
}
