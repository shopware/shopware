<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation\Collection\ListingSortingTranslationBasicCollection;
use Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation\Collection\ListingSortingTranslationDetailCollection;
use Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation\Event\ListingSortingTranslationDeletedEvent;
use Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation\Event\ListingSortingTranslationWrittenEvent;
use Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation\ListingSortingTranslationRepository;
use Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation\Struct\ListingSortingTranslationBasicStruct;
use Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation\Struct\ListingSortingTranslationDetailStruct;
use Shopware\Core\System\Listing\ListingSortingDefinition;

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
