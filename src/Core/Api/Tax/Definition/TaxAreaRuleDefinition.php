<?php declare(strict_types=1);

namespace Shopware\Api\Tax\Definition;

use Shopware\Api\Country\Definition\CountryAreaDefinition;
use Shopware\Api\Country\Definition\CountryDefinition;
use Shopware\Api\Country\Definition\CountryStateDefinition;
use Shopware\Api\Customer\Definition\CustomerGroupDefinition;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
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
use Shopware\Api\Entity\Write\Flag\SearchRanking;
use Shopware\Api\Tax\Collection\TaxAreaRuleBasicCollection;
use Shopware\Api\Tax\Collection\TaxAreaRuleDetailCollection;
use Shopware\Api\Tax\Event\TaxAreaRule\TaxAreaRuleDeletedEvent;
use Shopware\Api\Tax\Event\TaxAreaRule\TaxAreaRuleWrittenEvent;
use Shopware\Api\Tax\Repository\TaxAreaRuleRepository;
use Shopware\Api\Tax\Struct\TaxAreaRuleBasicStruct;
use Shopware\Api\Tax\Struct\TaxAreaRuleDetailStruct;

class TaxAreaRuleDefinition extends EntityDefinition
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
        return 'tax_area_rule';
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

            (new FkField('tax_id', 'taxId', TaxDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(TaxDefinition::class))->setFlags(new Required()),

            new FkField('country_area_id', 'countryAreaId', CountryAreaDefinition::class),
            new ReferenceVersionField(CountryAreaDefinition::class),

            new FkField('country_id', 'countryId', CountryDefinition::class),
            new ReferenceVersionField(CountryDefinition::class),

            new FkField('country_state_id', 'countryStateId', CountryStateDefinition::class),
            new ReferenceVersionField(CountryStateDefinition::class),

            (new FkField('customer_group_id', 'customerGroupId', CustomerGroupDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(CustomerGroupDefinition::class))->setFlags(new Required()),

            (new FloatField('tax_rate', 'taxRate'))->setFlags(new Required()),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new BoolField('active', 'active'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('countryArea', 'country_area_id', CountryAreaDefinition::class, false),
            new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, false),
            new ManyToOneAssociationField('countryState', 'country_state_id', CountryStateDefinition::class, false),
            new ManyToOneAssociationField('tax', 'tax_id', TaxDefinition::class, false),
            new ManyToOneAssociationField('customerGroup', 'customer_group_id', CustomerGroupDefinition::class, false),
            (new TranslationsAssociationField('translations', TaxAreaRuleTranslationDefinition::class, 'tax_area_rule_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return TaxAreaRuleRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return TaxAreaRuleBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return TaxAreaRuleDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return TaxAreaRuleWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return TaxAreaRuleBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return TaxAreaRuleTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return TaxAreaRuleDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return TaxAreaRuleDetailCollection::class;
    }
}
