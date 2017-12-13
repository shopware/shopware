<?php declare(strict_types=1);

namespace Shopware\Listing\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Listing\Collection\ListingSortingBasicCollection;
use Shopware\Listing\Collection\ListingSortingDetailCollection;
use Shopware\Listing\Event\ListingSorting\ListingSortingWrittenEvent;
use Shopware\Listing\Repository\ListingSortingRepository;
use Shopware\Listing\Struct\ListingSortingBasicStruct;
use Shopware\Listing\Struct\ListingSortingDetailStruct;
use Shopware\Product\Definition\ProductStreamDefinition;

class ListingSortingDefinition extends EntityDefinition
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
        return 'listing_sorting';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new LongTextField('payload', 'payload'))->setFlags(new Required()),
            (new TranslatedField(new StringField('label', 'label')))->setFlags(new Required()),
            new BoolField('active', 'active'),
            new BoolField('display_in_categories', 'displayInCategories'),
            new IntField('position', 'position'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            (new TranslationsAssociationField('translations', ListingSortingTranslationDefinition::class, 'listing_sorting_uuid', false, 'uuid'))->setFlags(new Required()),
            new OneToManyAssociationField('productStreams', ProductStreamDefinition::class, 'listing_sorting_uuid', false, 'uuid'),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ListingSortingRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ListingSortingBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ListingSortingWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ListingSortingBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return ListingSortingTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return ListingSortingDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ListingSortingDetailCollection::class;
    }
}
