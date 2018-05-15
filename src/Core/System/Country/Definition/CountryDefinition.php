<?php declare(strict_types=1);

namespace Shopware\System\Country\Definition;

use Shopware\Application\Application\Definition\ApplicationDefinition;
use Shopware\System\Country\Collection\CountryBasicCollection;
use Shopware\System\Country\Collection\CountryDetailCollection;
use Shopware\System\Country\Event\Country\CountryDeletedEvent;
use Shopware\System\Country\Event\Country\CountryWrittenEvent;
use Shopware\System\Country\Repository\CountryRepository;
use Shopware\System\Country\Struct\CountryBasicStruct;
use Shopware\System\Country\Struct\CountryDetailStruct;
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
use Shopware\Api\Shop\Definition\ShopDefinition;
use Shopware\System\Tax\Definition\TaxAreaRuleDefinition;

class CountryDefinition extends EntityDefinition
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
        return 'country';
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
            new FkField('country_area_id', 'areaId', CountryAreaDefinition::class),
            new ReferenceVersionField(CountryAreaDefinition::class),

            (new TranslatedField(new StringField('name', 'name')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new StringField('iso', 'iso'))->setFlags(new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            new IntField('position', 'position'),
            new BoolField('shipping_free', 'shippingFree'),
            new BoolField('tax_free', 'taxFree'),
            new BoolField('taxfree_for_vat_id', 'taxfreeForVatId'),
            new BoolField('taxfree_vatid_checked', 'taxfreeVatidChecked'),
            new BoolField('active', 'active'),
            (new StringField('iso3', 'iso3'))->setFlags(new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            new BoolField('display_state_in_registration', 'displayStateInRegistration'),
            new BoolField('force_state_in_registration', 'forceStateInRegistration'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('area', 'country_area_id', CountryAreaDefinition::class, false),
            (new OneToManyAssociationField('applications', ApplicationDefinition::class, 'country_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new OneToManyAssociationField('states', CountryStateDefinition::class, 'country_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('translations', CountryTranslationDefinition::class, 'country_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
            (new OneToManyAssociationField('customerAddresses', CustomerAddressDefinition::class, 'country_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
            (new OneToManyAssociationField('orderAddresses', OrderAddressDefinition::class, 'country_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
            (new OneToManyAssociationField('shops', ShopDefinition::class, 'country_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
            (new OneToManyAssociationField('taxAreaRules', TaxAreaRuleDefinition::class, 'country_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return CountryRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return CountryBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return CountryDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return CountryWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return CountryBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return CountryTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return CountryDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return CountryDetailCollection::class;
    }
}
