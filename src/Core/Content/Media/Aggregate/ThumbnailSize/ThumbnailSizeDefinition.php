<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\ThumbnailSize;

use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfigurationThumbnailSize\MediaFolderConfigurationThumbnailSizeDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class ThumbnailSizeDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'thumbnail_size';
    }

    public static function getCollectionClass(): string
    {
        return ThumbnailSizeCollection::class;
    }

    public static function getStructClass(): string
    {
        return ThumbnailSizeStruct::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),

            (new IntField('width', 'width'))->setFlags(new Required()),
            (new IntField('height', 'height'))->setFlags(new Required()),

            new ManyToManyAssociationField(
                'mediaFolderConfigurations',
                MediaFolderConfigurationDefinition::class,
                MediaFolderConfigurationThumbnailSizeDefinition::class,
                false,
                'thumbnail_size_id',
                'media_folder_configuration_id'
            ),
        ]);
    }
}
