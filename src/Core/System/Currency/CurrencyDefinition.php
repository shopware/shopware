<?php declare(strict_types=1);

namespace Shopware\System\Currency;

use Shopware\System\Touchpoint\TouchpointDefinition;
use Shopware\Checkout\Order\OrderDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\BoolField;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FloatField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\IntField;
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
use Shopware\Framework\ORM\Write\Flag\RestrictDelete;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Framework\ORM\Write\Flag\WriteOnly;
use Shopware\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationDefinition;
use Shopware\System\Currency\Collection\CurrencyBasicCollection;
use Shopware\System\Currency\Collection\CurrencyDetailCollection;
use Shopware\System\Currency\Event\CurrencyDeletedEvent;
use Shopware\System\Currency\Event\CurrencyWrittenEvent;
use Shopware\System\Currency\Struct\CurrencyBasicStruct;
use Shopware\System\Currency\Struct\CurrencyDetailStruct;

class CurrencyDefinition extends EntityDefinition
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
        return 'currency';
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
            (new FloatField('factor', 'factor'))->setFlags(new Required()),
            (new StringField('symbol', 'symbol'))->setFlags(new Required()),
            (new TranslatedField(new StringField('short_name', 'shortName')))->setFlags(new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new BoolField('is_default', 'isDefault'),
            new IntField('symbol_position', 'symbolPosition'),
            new IntField('position', 'position'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            (new OneToManyAssociationField('touchpoints', TouchpointDefinition::class, 'currency_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new TranslationsAssociationField('translations', CurrencyTranslationDefinition::class, 'currency_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
            (new OneToManyAssociationField('orders', OrderDefinition::class, 'currency_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return CurrencyRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return CurrencyBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return CurrencyDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return CurrencyWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return CurrencyBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return CurrencyTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return CurrencyDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return CurrencyDetailCollection::class;
    }
}
