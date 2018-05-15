<?php declare(strict_types=1);

namespace Shopware\Content\Media\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\CatalogField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Application\Language\Definition\LanguageDefinition;
use Shopware\Content\Media\Collection\MediaAlbumTranslationBasicCollection;
use Shopware\Content\Media\Collection\MediaAlbumTranslationDetailCollection;
use Shopware\Content\Media\Event\MediaAlbumTranslation\MediaAlbumTranslationDeletedEvent;
use Shopware\Content\Media\Event\MediaAlbumTranslation\MediaAlbumTranslationWrittenEvent;
use Shopware\Content\Media\Repository\MediaAlbumTranslationRepository;
use Shopware\Content\Media\Struct\MediaAlbumTranslationBasicStruct;
use Shopware\Content\Media\Struct\MediaAlbumTranslationDetailStruct;

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
