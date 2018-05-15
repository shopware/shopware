<?php declare(strict_types=1);

namespace Shopware\Content\Media\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\CatalogField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Entity\Write\Flag\SearchRanking;
use Shopware\Content\Media\Collection\MediaAlbumBasicCollection;
use Shopware\Content\Media\Collection\MediaAlbumDetailCollection;
use Shopware\Content\Media\Event\MediaAlbum\MediaAlbumDeletedEvent;
use Shopware\Content\Media\Event\MediaAlbum\MediaAlbumWrittenEvent;
use Shopware\Content\Media\Repository\MediaAlbumRepository;
use Shopware\Content\Media\Struct\MediaAlbumBasicStruct;
use Shopware\Content\Media\Struct\MediaAlbumDetailStruct;

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
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            new CatalogField(),
            new FkField('parent_id', 'parentId', self::class),
            new ReferenceVersionField(self::class, 'parent_version_id'),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new IntField('position', 'position'),
            new BoolField('create_thumbnails', 'createThumbnails'),
            new LongTextField('thumbnail_size', 'thumbnailSize'),
            new StringField('icon', 'icon'),
            new BoolField('thumbnail_high_dpi', 'thumbnailHighDpi'),
            new IntField('thumbnail_quality', 'thumbnailQuality'),
            new IntField('thumbnail_high_dpi_quality', 'thumbnailHighDpiQuality'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('parent', 'parent_id', self::class, false),
            (new OneToManyAssociationField('media', MediaDefinition::class, 'media_album_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new OneToManyAssociationField('children', self::class, 'parent_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('translations', MediaAlbumTranslationDefinition::class, 'media_album_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
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

    public static function getDeletedEventClass(): string
    {
        return MediaAlbumDeletedEvent::class;
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
