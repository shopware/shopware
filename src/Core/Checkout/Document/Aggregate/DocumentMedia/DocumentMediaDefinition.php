<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentMedia;

use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class DocumentMediaDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'document_media';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return DocumentMediaCollection::class;
    }

    public function getEntityClass(): string
    {
        return DocumentMediaEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection(
            [
                (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
                (new FkField('document_id', 'documentId', DocumentDefinition::class))->addFlags(new ApiAware(), new Required()),
                (new FkField('media_id', 'mediaId', MediaDefinition::class))->addFlags(new ApiAware(), new Required()),
                (new StringField('file_extension', 'fileExtension'))->addFlags(new ApiAware(), new Required()),
                (new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, 'id'))->addFlags(new ApiAware()),
                (new ManyToOneAssociationField('document', 'document_id', DocumentDefinition::class, 'id'))->addFlags(new ApiAware()),
                (new CustomFields())->addFlags(new ApiAware()),
            ]
        );
    }
}
