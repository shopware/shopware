<?php declare(strict_types=1);

namespace Shopware\Api\Country\Definition;

use Shopware\Api\Country\Collection\CountryStateBasicCollection;
use Shopware\Api\Country\Collection\CountryStateDetailCollection;
use Shopware\Api\Country\Event\CountryState\CountryStateDeletedEvent;
use Shopware\Api\Country\Event\CountryState\CountryStateWrittenEvent;
use Shopware\Api\Country\Repository\CountryStateRepository;
use Shopware\Api\Country\Struct\CountryStateBasicStruct;
use Shopware\Api\Country\Struct\CountryStateDetailStruct;
use Shopware\Api\Customer\Definition\CustomerAddressDefinition;
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
use Shopware\Api\Tax\Definition\TaxAreaRuleDefinition;

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
