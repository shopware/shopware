<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration;

use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfigurationMediaThumbnailSize\MediaFolderConfigurationMediaThumbnailSizeDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeDefinition;
use Shopware\Core\Framework\Context\AdminApiSource;
use Shopware\Core\Framework\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

class MediaFolderConfigurationDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'media_folder_configuration';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return MediaFolderConfigurationCollection::class;
    }

    public function getEntityClass(): string
    {
        return MediaFolderConfigurationEntity::class;
    }

    public function getDefaults(EntityExistence $existence): array
    {
        return [
            'createThumbnails' => true,
            'keepAspectRatio' => true,
            'thumbnailQuality' => 80,
            'private' => false,
            'noAssociation' => false,
        ];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            new BoolField('create_thumbnails', 'createThumbnails'),
            new BoolField('keep_aspect_ratio', 'keepAspectRatio'),
            new IntField('thumbnail_quality', 'thumbnailQuality', 0, 100),
            new BoolField('private', 'private'),
            new BoolField('no_association', 'noAssociation'),

            new OneToManyAssociationField(
                'mediaFolders',
                MediaFolderDefinition::class,
                'media_folder_configuration_id',
                'id'
            ),

            new ManyToManyAssociationField(
                'mediaThumbnailSizes',
                MediaThumbnailSizeDefinition::class,
                MediaFolderConfigurationMediaThumbnailSizeDefinition::class,
                'media_folder_configuration_id',
                'media_thumbnail_size_id'
            ),

            (new BlobField('media_thumbnail_sizes_ro', 'mediaThumbnailSizesRo'))->addFlags(new Computed(), new ReadProtected(SalesChannelApiSource::class, AdminApiSource::class)),

            new CustomFields(),
        ]);
    }
}
