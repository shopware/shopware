<?php declare(strict_types=1);

namespace Shopware\Content\Media\Aggregate\MediaAlbum;

use Shopware\Content\Media\Aggregate\MediaAlbumTranslation\MediaAlbumTranslationDefinition;
use Shopware\Content\Media\MediaDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\BoolField;
use Shopware\Framework\ORM\Field\CatalogField;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\IntField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\TranslatedField;
use Shopware\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\RestrictDelete;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Content\Media\Aggregate\MediaAlbum\Collection\MediaAlbumBasicCollection;
use Shopware\Content\Media\Aggregate\MediaAlbum\Collection\MediaAlbumDetailCollection;
use Shopware\Content\Media\Aggregate\MediaAlbum\Event\MediaAlbumDeletedEvent;
use Shopware\Content\Media\Aggregate\MediaAlbum\Event\MediaAlbumWrittenEvent;

use Shopware\Content\Media\Aggregate\MediaAlbum\Struct\MediaAlbumBasicStruct;
use Shopware\Content\Media\Aggregate\MediaAlbum\Struct\MediaAlbumDetailStruct;

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
