<?php declare(strict_types=1);

namespace Shopware\Customer\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Write\Flag\PrimaryKey;
use Shopware\Api\Write\Flag\Required;
use Shopware\Country\Definition\CountryDefinition;
use Shopware\Country\Definition\CountryStateDefinition;
use Shopware\Customer\Collection\CustomerAddressBasicCollection;
use Shopware\Customer\Collection\CustomerAddressDetailCollection;
use Shopware\Customer\Event\CustomerAddress\CustomerAddressWrittenEvent;
use Shopware\Customer\Repository\CustomerAddressRepository;
use Shopware\Customer\Struct\CustomerAddressBasicStruct;
use Shopware\Customer\Struct\CustomerAddressDetailStruct;

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
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('customer_uuid', 'customerUuid', CustomerDefinition::class))->setFlags(new Required()),
            (new FkField('country_uuid', 'countryUuid', CountryDefinition::class))->setFlags(new Required()),
            new FkField('country_state_uuid', 'countryStateUuid', CountryStateDefinition::class),
            (new StringField('salutation', 'salutation'))->setFlags(new Required()),
            (new StringField('first_name', 'firstName'))->setFlags(new Required()),
            (new StringField('last_name', 'lastName'))->setFlags(new Required()),
            (new StringField('zipcode', 'zipcode'))->setFlags(new Required()),
            (new StringField('city', 'city'))->setFlags(new Required()),
            new StringField('company', 'company'),
            new StringField('department', 'department'),
            new StringField('title', 'title'),
            new StringField('street', 'street'),
            new StringField('vat_id', 'vatId'),
            new StringField('phone_number', 'phoneNumber'),
            new StringField('additional_address_line1', 'additionalAddressLine1'),
            new StringField('additional_address_line2', 'additionalAddressLine2'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('customer', 'customer_uuid', CustomerDefinition::class, false),
            new ManyToOneAssociationField('country', 'country_uuid', CountryDefinition::class, true),
            new ManyToOneAssociationField('countryState', 'country_state_uuid', CountryStateDefinition::class, true),
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
