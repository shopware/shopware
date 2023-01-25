<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel;

use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

#[Package('customer-order')]
class DocumentBaseConfigSalesChannelDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'document_base_config_sales_channel';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getCollectionClass(): string
    {
        return DocumentBaseConfigSalesChannelCollection::class;
    }

    public function getEntityClass(): string
    {
        return DocumentBaseConfigSalesChannelEntity::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return DocumentBaseConfigDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new FkField('document_base_config_id', 'documentBaseConfigId', DocumentBaseConfigDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new ApiAware()),
            (new FkField('document_type_id', 'documentTypeId', DocumentTypeDefinition::class))->addFlags(new ApiAware()),
            new ManyToOneAssociationField('documentType', 'document_type_id', DocumentTypeDefinition::class, 'id'),
            new ManyToOneAssociationField('documentBaseConfig', 'document_base_config_id', DocumentBaseConfigDefinition::class, 'id'),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id'),
        ]);
    }
}
