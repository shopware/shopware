<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel;

use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Deprecated;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
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
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of document\'s base config sales channel.'),
            (new FkField('document_base_config_id', 'documentBaseConfigId', DocumentBaseConfigDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of document\'s base config.'),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of sales channel.'),
            (new StringField('type_name', 'typeName'))->addFlags(new ApiAware(), new Deprecated('v6.7.15.0', 'v6.8.0.0', 'documentBaseConfig.typeName'))->setDescription('Technical name of the document type.'),
            new ManyToOneAssociationField('documentBaseConfig', 'document_base_config_id', DocumentBaseConfigDefinition::class, 'id'),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id'),

            (new FkField('document_type_id', 'documentTypeId', DocumentTypeDefinition::class))->addFlags(new ApiAware(), new Deprecated('v6.7.14.0', 'v6.9.0.0', 'typeName'))->setDescription('Unique identity of document type.'),
            (new ManyToOneAssociationField('documentType', 'document_type_id', DocumentTypeDefinition::class, 'id'))->addFlags(new Deprecated('v6.7.14.0', 'v6.9.0.0', 'documentBaseConfig.typeName')),
        ]);
    }
}
