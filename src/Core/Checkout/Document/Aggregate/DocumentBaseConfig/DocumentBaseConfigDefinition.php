<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig;

use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;

class DocumentBaseConfigDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'document_base_config';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return DocumentBaseConfigCollection::class;
    }

    public function getEntityClass(): string
    {
        return DocumentBaseConfigEntity::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return DocumentTypeDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('document_type_id', 'documentTypeId', DocumentTypeDefinition::class))->addFlags(new Required()),
            new FkField('logo_id', 'logoId', DocumentTypeDefinition::class),

            (new StringField('name', 'name'))->addFlags(new Required()),
            new StringField('filename_prefix', 'filenamePrefix'),
            new StringField('filename_suffix', 'filenameSuffix'),
            (new BoolField('global', 'global'))->addFlags(new Required()),
            new NumberRangeField('document_number', 'documentNumber'),
            new JsonField('config', 'config'),
            new CreatedAtField(),

            (new ManyToOneAssociationField('documentType', 'document_type_id', DocumentTypeDefinition::class, 'id')),
            new ManyToOneAssociationField('logo', 'logo_id', MediaDefinition::class, 'id'),
            (new OneToManyAssociationField('salesChannels', DocumentBaseConfigSalesChannelDefinition::class, 'document_base_config_id', 'id'))->addFlags(new CascadeDelete()),
        ]);
    }
}
