<?php declare(strict_types=1);

namespace Shopware\System\Country\Definition;

use Shopware\Api\Application\Definition\ApplicationDefinition;
use Shopware\System\Country\Collection\CountryBasicCollection;
use Shopware\System\Country\Collection\CountryDetailCollection;
use Shopware\System\Country\Event\Country\CountryDeletedEvent;
use Shopware\System\Country\Event\Country\CountryWrittenEvent;
use Shopware\System\Country\Repository\CountryRepository;
use Shopware\System\Country\Struct\CountryBasicStruct;
use Shopware\System\Country\Struct\CountryDetailStruct;
use Shopware\Checkout\Customer\Definition\CustomerAddressDefinition;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Entity\Write\Flag\SearchRanking;
use Shopware\Api\Entity\Write\Flag\WriteOnly;
use Shopware\Api\Order\Definition\OrderAddressDefinition;
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
