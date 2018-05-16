<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Definition;

use Shopware\System\Country\CountryDefinition;
use Shopware\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\Checkout\Customer\Collection\CustomerAddressBasicCollection;
use Shopware\Checkout\Customer\Collection\CustomerAddressDetailCollection;
use Shopware\Checkout\Customer\Event\CustomerAddress\CustomerAddressDeletedEvent;
use Shopware\Checkout\Customer\Event\CustomerAddress\CustomerAddressWrittenEvent;
use Shopware\Checkout\Customer\Repository\CustomerAddressRepository;
use Shopware\Checkout\Customer\Struct\CustomerAddressBasicStruct;
use Shopware\Checkout\Customer\Struct\CustomerAddressDetailStruct;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;

class CustomerAddressDefinition extends EntityDefinition
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
        return 'customer_address';
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

            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(CustomerDefinition::class))->setFlags(new Required()),

            (new FkField('country_id', 'countryId', \Shopware\System\Country\CountryDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(\Shopware\System\Country\CountryDefinition::class))->setFlags(new Required()),

            new FkField('country_state_id', 'countryStateId', \Shopware\System\Country\Aggregate\CountryState\CountryStateDefinition::class),
            new ReferenceVersionField(CountryStateDefinition::class),

            (new StringField('salutation', 'salutation'))->setFlags(new Required()),
            (new StringField('first_name', 'firstName'))->setFlags(new Required(), new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new StringField('last_name', 'lastName'))->setFlags(new Required(), new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new StringField('zipcode', 'zipcode'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new StringField('city', 'city'))->setFlags(new Required(), new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new StringField('company', 'company'))->setFlags(new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new StringField('street', 'street'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new StringField('department', 'department'),
            new StringField('title', 'title'),
            new StringField('vat_id', 'vatId'),
            new StringField('phone_number', 'phoneNumber'),
            (new StringField('additional_address_line1', 'additionalAddressLine1'))->setFlags(new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new StringField('additional_address_line2', 'additionalAddressLine2'))->setFlags(new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, false),
            new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, true),
            new ManyToOneAssociationField('countryState', 'country_state_id', CountryStateDefinition::class, true),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return CustomerAddressRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return CustomerAddressBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return CustomerAddressDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return CustomerAddressWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return CustomerAddressBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return CustomerAddressDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return CustomerAddressDetailCollection::class;
    }
}
