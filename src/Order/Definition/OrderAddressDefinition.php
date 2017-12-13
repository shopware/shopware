<?php declare(strict_types=1);

namespace Shopware\Order\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Country\Definition\CountryDefinition;
use Shopware\Country\Definition\CountryStateDefinition;
use Shopware\Order\Collection\OrderAddressBasicCollection;
use Shopware\Order\Collection\OrderAddressDetailCollection;
use Shopware\Order\Event\OrderAddress\OrderAddressWrittenEvent;
use Shopware\Order\Repository\OrderAddressRepository;
use Shopware\Order\Struct\OrderAddressBasicStruct;
use Shopware\Order\Struct\OrderAddressDetailStruct;

class OrderAddressDefinition extends EntityDefinition
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
        return 'order_address';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('country_uuid', 'countryUuid', CountryDefinition::class))->setFlags(new Required()),
            new FkField('country_state_uuid', 'countryStateUuid', CountryStateDefinition::class),
            (new StringField('salutation', 'salutation'))->setFlags(new Required()),
            (new StringField('first_name', 'firstName'))->setFlags(new Required()),
            (new StringField('last_name', 'lastName'))->setFlags(new Required()),
            (new StringField('street', 'street'))->setFlags(new Required()),
            (new StringField('zipcode', 'zipcode'))->setFlags(new Required()),
            (new StringField('city', 'city'))->setFlags(new Required()),
            new StringField('company', 'company'),
            new StringField('department', 'department'),
            new StringField('title', 'title'),
            new StringField('vat_id', 'vatId'),
            new StringField('phone_number', 'phoneNumber'),
            new StringField('additional_address_line1', 'additionalAddressLine1'),
            new StringField('additional_address_line2', 'additionalAddressLine2'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('country', 'country_uuid', CountryDefinition::class, true),
            new ManyToOneAssociationField('countryState', 'country_state_uuid', CountryStateDefinition::class, true),
            new OneToManyAssociationField('orders', OrderDefinition::class, 'billing_address_uuid', false, 'uuid'),
            new OneToManyAssociationField('orderDeliveries', OrderDeliveryDefinition::class, 'shipping_address_uuid', false, 'uuid'),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return OrderAddressRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return OrderAddressBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return OrderAddressWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return OrderAddressBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return OrderAddressDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return OrderAddressDetailCollection::class;
    }
}
