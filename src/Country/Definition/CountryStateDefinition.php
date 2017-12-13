<?php declare(strict_types=1);

namespace Shopware\Country\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Country\Collection\CountryStateBasicCollection;
use Shopware\Country\Collection\CountryStateDetailCollection;
use Shopware\Country\Event\CountryState\CountryStateWrittenEvent;
use Shopware\Country\Repository\CountryStateRepository;
use Shopware\Country\Struct\CountryStateBasicStruct;
use Shopware\Country\Struct\CountryStateDetailStruct;
use Shopware\Customer\Definition\CustomerAddressDefinition;
use Shopware\Order\Definition\OrderAddressDefinition;
use Shopware\Tax\Definition\TaxAreaRuleDefinition;

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
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('country_uuid', 'countryUuid', CountryDefinition::class))->setFlags(new Required()),
            (new StringField('short_code', 'shortCode'))->setFlags(new Required()),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new Required()),
            new IntField('position', 'position'),
            new BoolField('active', 'active'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('country', 'country_uuid', CountryDefinition::class, false),
            (new TranslationsAssociationField('translations', CountryStateTranslationDefinition::class, 'country_state_uuid', false, 'uuid'))->setFlags(new Required()),
            new OneToManyAssociationField('customerAddresses', CustomerAddressDefinition::class, 'country_state_uuid', false, 'uuid'),
            new OneToManyAssociationField('orderAddresses', OrderAddressDefinition::class, 'country_state_uuid', false, 'uuid'),
            new OneToManyAssociationField('taxAreaRules', TaxAreaRuleDefinition::class, 'country_state_uuid', false, 'uuid'),
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
