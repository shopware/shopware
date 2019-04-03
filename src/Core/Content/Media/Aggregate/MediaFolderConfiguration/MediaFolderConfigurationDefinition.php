<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration;

use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfigurationMediaThumbnailSize\MediaFolderConfigurationMediaThumbnailSizeDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

class MediaFolderConfigurationDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'media_folder_configuration';
    }

    public static function getCollectionClass(): string
    {
        return MediaFolderConfigurationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return MediaFolderConfigurationEntity::class;
    }

    public static function getDefaults(EntityExistence $existence): array
    {
        if ($existence->exists()) {
            return [];
        }

        return [
            'createThumbnails' => true,
            'keepAspectRatio' => true,
            'thumbnailQuality' => 80,
        ];
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            new BoolField('create_thumbnails', 'createThumbnails'),
            new BoolField('keep_aspect_ratio', 'keepAspectRatio'),
            new IntField('thumbnail_quality', 'thumbnailQuality', 0, 100),

            new OneToManyAssociationField(
                'mediaFolders',
                MediaFolderDefinition::class,
                'media_folder_configuration_id',
                false),

            new ManyToManyAssociationField(
                'mediaThumbnailSizes',
                MediaThumbnailSizeDefinition::class,
                MediaFolderConfigurationMediaThumbnailSizeDefinition::class,
                'media_folder_configuration_id',
                'media_thumbnail_size_id'
            ),

            new CreatedAtField(),
            new UpdatedAtField(),
            new AttributesField(),
        ]);
    }
}
