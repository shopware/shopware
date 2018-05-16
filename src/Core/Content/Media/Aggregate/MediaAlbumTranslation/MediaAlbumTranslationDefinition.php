<?php declare(strict_types=1);

namespace Shopware\Content\Media\Aggregate\MediaAlbumTranslation;

use Shopware\Content\Media\Aggregate\MediaAlbum\MediaAlbumDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\CatalogField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Application\Language\LanguageDefinition;
use Shopware\Content\Media\Aggregate\MediaAlbumTranslation\Collection\MediaAlbumTranslationBasicCollection;
use Shopware\Content\Media\Aggregate\MediaAlbumTranslation\Collection\MediaAlbumTranslationDetailCollection;
use Shopware\Content\Media\Aggregate\MediaAlbumTranslation\Event\MediaAlbumTranslationDeletedEvent;
use Shopware\Content\Media\Aggregate\MediaAlbumTranslation\Event\MediaAlbumTranslationWrittenEvent;
use Shopware\Content\Media\Aggregate\MediaAlbumTranslation\MediaAlbumTranslationRepository;
use Shopware\Content\Media\Aggregate\MediaAlbumTranslation\Struct\MediaAlbumTranslationBasicStruct;
use Shopware\Content\Media\Aggregate\MediaAlbumTranslation\Struct\MediaAlbumTranslationDetailStruct;

class MediaAlbumTranslationDefinition extends EntityDefinition
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
        return 'media_album_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new FkField('media_album_id', 'mediaAlbumId', MediaAlbumDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(MediaAlbumDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            new CatalogField(),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new ManyToOneAssociationField('mediaAlbum', 'media_album_id', MediaAlbumDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return MediaAlbumTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return MediaAlbumTranslationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return MediaAlbumTranslationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return MediaAlbumTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return MediaAlbumTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return MediaAlbumTranslationDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return MediaAlbumTranslationDetailCollection::class;
    }
}
