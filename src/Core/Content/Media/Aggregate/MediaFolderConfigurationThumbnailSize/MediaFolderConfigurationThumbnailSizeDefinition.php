<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaFolderConfigurationThumbnailSize;

use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationDefinition;
use Shopware\Core\Content\Media\Aggregate\ThumbnailSize\ThumbnailSizeDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class MediaFolderConfigurationThumbnailSizeDefinition extends MappingEntityDefinition
{
    public static function getEntityName(): string
    {
        return 'media_folder_configuration_thumbnail_size';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('media_folder_configuration_id', 'mediaFolderConfigurationId', MediaFolderConfigurationDefinition::class))
                ->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(MediaFolderConfigurationDefinition::class, 'media_folder_configuration_version_id'))
                ->setFlags(new PrimaryKey(), new Required()),
            (new FkField('thumbnail_size_id', 'thumbnailSizeId', ThumbnailSizeDefinition::class))
                ->setFlags(new PrimaryKey(), new Required()),

            new CreatedAtField(),
            new ManyToOneAssociationField('mediaFolderConfiguration', 'media_folder_configuration_id', MediaFolderConfigurationDefinition::class, false),
            new ManyToOneAssociationField('thumbnailSize', 'thumbnail_size_id', ThumbnailSizeDefinition::class, false),
        ]);
    }
}
