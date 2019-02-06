<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder;

use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
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

            (new ListField('associations', 'associations', StringField::class))->addFlags(new Required()),
            (new StringField('entity', 'entity'))->addFlags(new Required()),

            new FkField('media_folder_id', 'folderId', MediaFolderDefinition::class),

            new ManyToOneAssociationField('folder', 'media_folder_id', MediaFolderDefinition::class, true),

            new CreatedAtField(),
            new UpdatedAtField(),

            new AttributesField(),
        ]);
    }
}
