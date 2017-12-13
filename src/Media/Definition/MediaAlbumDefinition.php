<?php declare(strict_types=1);

namespace Shopware\Media\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Media\Collection\MediaAlbumBasicCollection;
use Shopware\Media\Collection\MediaAlbumDetailCollection;
use Shopware\Media\Event\MediaAlbum\MediaAlbumWrittenEvent;
use Shopware\Media\Repository\MediaAlbumRepository;
use Shopware\Media\Struct\MediaAlbumBasicStruct;
use Shopware\Media\Struct\MediaAlbumDetailStruct;

class MediaAlbumDefinition extends EntityDefinition
{
    /**
     * @var FieldCollection
     */
    protected static $primaryKeys;

    /**
     * @var FieldCollection
     */
    protected static $fields;

    /**
     * @var EntityExtensionInterface[]
     */
    protected static $extensions = [];

    public static function getEntityName(): string
    {
        return 'media_album';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            new FkField('parent_uuid', 'parentUuid', self::class),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new Required()),
            new IntField('position', 'position'),
            new BoolField('create_thumbnails', 'createThumbnails'),
            new LongTextField('thumbnail_size', 'thumbnailSize'),
            new StringField('icon', 'icon'),
            new BoolField('thumbnail_high_dpi', 'thumbnailHighDpi'),
            new IntField('thumbnail_quality', 'thumbnailQuality'),
            new IntField('thumbnail_high_dpi_quality', 'thumbnailHighDpiQuality'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('parent', 'parent_uuid', self::class, false),
            new OneToManyAssociationField('media', MediaDefinition::class, 'media_album_uuid', false, 'uuid'),
            (new TranslationsAssociationField('translations', MediaAlbumTranslationDefinition::class, 'media_album_uuid', false, 'uuid'))->setFlags(new Required()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return MediaAlbumRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return MediaAlbumBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return MediaAlbumWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return MediaAlbumBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return MediaAlbumTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return MediaAlbumDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return MediaAlbumDetailCollection::class;
    }
}
