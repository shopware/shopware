<?php declare(strict_types=1);

namespace Shopware\System\Listing\Definition;

use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\BoolField;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\IntField;
use Shopware\Framework\ORM\Field\JsonArrayField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\TranslatedField;
use Shopware\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Framework\ORM\Write\Flag\Serialized;
use Shopware\Framework\ORM\Write\Flag\WriteOnly;
use Shopware\System\Listing\Collection\ListingSortingBasicCollection;
use Shopware\System\Listing\Collection\ListingSortingDetailCollection;
use Shopware\System\Listing\Event\ListingSorting\ListingSortingDeletedEvent;
use Shopware\System\Listing\Event\ListingSorting\ListingSortingWrittenEvent;
use Shopware\System\Listing\Repository\ListingSortingRepository;
use Shopware\System\Listing\Struct\ListingSortingBasicStruct;
use Shopware\System\Listing\Struct\ListingSortingDetailStruct;
use Shopware\Content\Product\Aggregate\ProductStream\ProductStreamDefinition;

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
            new TenantIdField(),
            new VersionField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new JsonArrayField('payload', 'payload'))->setFlags(new Required(), new Serialized()),
            (new TranslatedField(new StringField('label', 'label')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new StringField('unique_key', 'uniqueKey'))->setFlags(new Required()),
            new BoolField('active', 'active'),
            new BoolField('display_in_categories', 'displayInCategories'),
            new IntField('position', 'position'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            (new TranslationsAssociationField('translations', ListingSortingTranslationDefinition::class, 'listing_sorting_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
            (new OneToManyAssociationField('productStreams', ProductStreamDefinition::class, 'listing_sorting_id', false, 'id'))->setFlags(new WriteOnly()),
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

    public static function getDeletedEventClass(): string
    {
        return ListingSortingDeletedEvent::class;
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
