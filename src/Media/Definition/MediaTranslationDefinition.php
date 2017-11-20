<?php declare(strict_types=1);

namespace Shopware\Media\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Write\Flag\PrimaryKey;
use Shopware\Api\Write\Flag\Required;
use Shopware\Media\Collection\MediaTranslationBasicCollection;
use Shopware\Media\Collection\MediaTranslationDetailCollection;
use Shopware\Media\Event\MediaTranslation\MediaTranslationWrittenEvent;
use Shopware\Media\Repository\MediaTranslationRepository;
use Shopware\Media\Struct\MediaTranslationBasicStruct;
use Shopware\Media\Struct\MediaTranslationDetailStruct;
use Shopware\Shop\Definition\ShopDefinition;

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
            (new FkField('media_uuid', 'mediaUuid', MediaDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_uuid', 'languageUuid', ShopDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new LongTextField('description', 'description'),
            new ManyToOneAssociationField('media', 'media_uuid', MediaDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_uuid', ShopDefinition::class, false),
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
