<?php declare(strict_types=1);

namespace Shopware\System\Listing\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Application\Language\Definition\LanguageDefinition;
use Shopware\System\Listing\Collection\ListingSortingTranslationBasicCollection;
use Shopware\System\Listing\Collection\ListingSortingTranslationDetailCollection;
use Shopware\System\Listing\Event\ListingSortingTranslation\ListingSortingTranslationDeletedEvent;
use Shopware\System\Listing\Event\ListingSortingTranslation\ListingSortingTranslationWrittenEvent;
use Shopware\System\Listing\Repository\ListingSortingTranslationRepository;
use Shopware\System\Listing\Struct\ListingSortingTranslationBasicStruct;
use Shopware\System\Listing\Struct\ListingSortingTranslationDetailStruct;

class ListingSortingTranslationDefinition extends EntityDefinition
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
        return 'listing_sorting_translation';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new FkField('listing_sorting_id', 'listingSortingId', ListingSortingDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(ListingSortingDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('label', 'label'))->setFlags(new Required()),
            new ManyToOneAssociationField('listingSorting', 'listing_sorting_id', ListingSortingDefinition::class, false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ListingSortingTranslationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ListingSortingTranslationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ListingSortingTranslationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ListingSortingTranslationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ListingSortingTranslationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ListingSortingTranslationDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ListingSortingTranslationDetailCollection::class;
    }
}
