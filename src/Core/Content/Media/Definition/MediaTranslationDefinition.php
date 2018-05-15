<?php declare(strict_types=1);

namespace Shopware\Content\Media\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\CatalogField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Application\Language\Definition\LanguageDefinition;
use Shopware\Content\Media\Collection\MediaTranslationBasicCollection;
use Shopware\Content\Media\Collection\MediaTranslationDetailCollection;
use Shopware\Content\Media\Event\MediaTranslation\MediaTranslationDeletedEvent;
use Shopware\Content\Media\Event\MediaTranslation\MediaTranslationWrittenEvent;
use Shopware\Content\Media\Repository\MediaTranslationRepository;
use Shopware\Content\Media\Struct\MediaTranslationBasicStruct;
use Shopware\Content\Media\Struct\MediaTranslationDetailStruct;

class MediaTranslationDefinition extends EntityDefinition
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
        return 'media_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new FkField('media_id', 'mediaId', MediaDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(MediaDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            new CatalogField(),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new LongTextField('description', 'description'),
            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return MediaTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return MediaTranslationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return MediaTranslationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return MediaTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return MediaTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return MediaTranslationDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return MediaTranslationDetailCollection::class;
    }
}
