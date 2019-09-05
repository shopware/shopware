<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Extension;

use Shopware\Core\Content\ProductExport\ProductExportDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;

class SalesChannelDomainExtension implements EntityExtensionInterface
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField('productExports', ProductExportDefinition::class, 'sales_channel_domain_id', 'id')
        );
    }

    public function getDefinitionClass(): string
    {
        return SalesChannelDomainDefinition::class;
    }
}
