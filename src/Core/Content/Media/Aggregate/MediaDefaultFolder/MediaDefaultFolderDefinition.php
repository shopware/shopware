<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder;

use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class MediaDefaultFolderDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'media_default_folder';
    }

    public static function getCollectionClass(): string
    {
        return MediaDefaultFolderCollection::class;
    }

    public static function getEntityClass(): string
    {
        return MediaDefaultFolderEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new ListField('association_fields', 'associationFields', StringField::class))->addFlags(new Required()),
            (new ListField('thumbnail_sizes', 'thumbnailSizes', JsonField::class))->addFlags(new Required()),

            (new StringField('entity', 'entity'))->addFlags(new Required()),

            new OneToOneAssociationField('folder', 'id', 'default_folder_id', MediaFolderDefinition::class, false),

            new CreatedAtField(),
            new UpdatedAtField(),

            new AttributesField(),
        ]);
    }
}
