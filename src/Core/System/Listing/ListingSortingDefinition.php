<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing;

use Shopware\Core\Content\Product\Aggregate\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\BoolField;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\IntField;
use Shopware\Core\Framework\ORM\Field\ListField;
use Shopware\Core\Framework\ORM\Field\ObjectField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Core\Framework\ORM\Write\Flag\WriteOnly;
use Shopware\Core\System\Listing\Collection\ListingSortingBasicCollection;
use Shopware\Core\System\Listing\Collection\ListingSortingDetailCollection;
use Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation\ListingSortingTranslationDefinition;
use Shopware\Core\System\Listing\Event\ListingSortingDeletedEvent;
use Shopware\Core\System\Listing\Event\ListingSortingWrittenEvent;
use Shopware\Core\System\Listing\Struct\ListingSortingBasicStruct;
use Shopware\Core\System\Listing\Struct\ListingSortingDetailStruct;

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
            (new ListField('payload', 'payload', ObjectField::class))->setFlags(new Required()),
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
