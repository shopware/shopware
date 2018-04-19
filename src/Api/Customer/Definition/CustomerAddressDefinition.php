<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Definition;

use Shopware\Api\Country\Definition\CountryDefinition;
use Shopware\Api\Country\Definition\CountryStateDefinition;
use Shopware\Api\Customer\Collection\CustomerAddressBasicCollection;
use Shopware\Api\Customer\Collection\CustomerAddressDetailCollection;
use Shopware\Api\Customer\Event\CustomerAddress\CustomerAddressDeletedEvent;
use Shopware\Api\Customer\Event\CustomerAddress\CustomerAddressWrittenEvent;
use Shopware\Api\Customer\Repository\CustomerAddressRepository;
use Shopware\Api\Customer\Struct\CustomerAddressBasicStruct;
use Shopware\Api\Customer\Struct\CustomerAddressDetailStruct;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\SearchRanking;

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

            (new FkField('country_id', 'countryId', CountryDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(CountryDefinition::class))->setFlags(new Required()),

            new FkField('country_state_id', 'countryStateId', CountryStateDefinition::class),
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
