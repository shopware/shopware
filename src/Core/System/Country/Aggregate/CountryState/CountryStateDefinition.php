<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryState;

use Shopware\System\Country\Aggregate\CountryState\Collection\CountryStateBasicCollection;
use Shopware\System\Country\Aggregate\CountryState\Collection\CountryStateDetailCollection;
use Shopware\System\Country\Aggregate\CountryState\Event\CountryStateDeletedEvent;
use Shopware\System\Country\Aggregate\CountryState\Event\CountryStateWrittenEvent;
use Shopware\System\Country\CountryDefinition;
use Shopware\System\Country\Aggregate\CountryStateTranslation\CountryStateTranslationDefinition;
use Shopware\System\Country\Aggregate\CountryState\CountryStateRepository;
use Shopware\System\Country\Aggregate\CountryState\Struct\CountryStateBasicStruct;
use Shopware\System\Country\Aggregate\CountryState\Struct\CountryStateDetailStruct;
use Shopware\Checkout\Customer\Definition\CustomerAddressDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\BoolField;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\IntField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
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
use Shopware\Checkout\Order\Definition\OrderAddressDefinition;
use Shopware\System\Tax\Definition\TaxAreaRuleDefinition;

class CountryStateDefinition extends EntityDefinition
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
        return 'country_state';
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

            (new FkField('country_id', 'countryId', CountryDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(CountryDefinition::class))->setFlags(new Required()),

            (new StringField('short_code', 'shortCode'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new IntField('position', 'position'),
            new BoolField('active', 'active'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, false),
            (new TranslationsAssociationField('translations', CountryStateTranslationDefinition::class, 'country_state_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
            (new OneToManyAssociationField('customerAddresses', CustomerAddressDefinition::class, 'country_state_id', false, 'id'))->setFlags(new WriteOnly()),
            (new OneToManyAssociationField('orderAddresses', OrderAddressDefinition::class, 'country_state_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
            (new OneToManyAssociationField('taxAreaRules', TaxAreaRuleDefinition::class, 'country_state_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return CountryStateRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return CountryStateBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return CountryStateDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return CountryStateWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return CountryStateBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return CountryStateTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return CountryStateDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return CountryStateDetailCollection::class;
    }
}
