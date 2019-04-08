<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize;

use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfigurationMediaThumbnailSize\MediaFolderConfigurationMediaThumbnailSizeDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class MediaThumbnailSizeDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'media_thumbnail_size';
    }

    public static function getCollectionClass(): string
    {
        return MediaThumbnailSizeCollection::class;
    }

    public static function getEntityClass(): string
    {
        return MediaThumbnailSizeEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new IntField('width', 'width', 1))->addFlags(new Required()),
            (new IntField('height', 'height', 1))->addFlags(new Required()),

            new ManyToManyAssociationField(
                'mediaFolderConfigurations',
                MediaFolderConfigurationDefinition::class,
                MediaFolderConfigurationMediaThumbnailSizeDefinition::class,
                'media_thumbnail_size_id',
                'media_folder_configuration_id'
            ),
            new AttributesField(),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
