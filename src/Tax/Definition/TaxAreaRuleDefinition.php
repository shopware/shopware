<?php declare(strict_types=1);

namespace Shopware\Tax\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Write\Flag\PrimaryKey;
use Shopware\Api\Write\Flag\Required;
use Shopware\Country\Definition\CountryAreaDefinition;
use Shopware\Country\Definition\CountryDefinition;
use Shopware\Country\Definition\CountryStateDefinition;
use Shopware\Customer\Definition\CustomerGroupDefinition;
use Shopware\Tax\Collection\TaxAreaRuleBasicCollection;
use Shopware\Tax\Collection\TaxAreaRuleDetailCollection;
use Shopware\Tax\Event\TaxAreaRule\TaxAreaRuleWrittenEvent;
use Shopware\Tax\Repository\TaxAreaRuleRepository;
use Shopware\Tax\Struct\TaxAreaRuleBasicStruct;
use Shopware\Tax\Struct\TaxAreaRuleDetailStruct;

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
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            new FkField('country_area_uuid', 'countryAreaUuid', CountryAreaDefinition::class),
            new FkField('country_uuid', 'countryUuid', CountryDefinition::class),
            new FkField('country_state_uuid', 'countryStateUuid', CountryStateDefinition::class),
            (new FkField('tax_uuid', 'taxUuid', TaxDefinition::class))->setFlags(new Required()),
            (new FkField('customer_group_uuid', 'customerGroupUuid', CustomerGroupDefinition::class))->setFlags(new Required()),
            (new FloatField('tax_rate', 'taxRate'))->setFlags(new Required()),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new Required()),
            new BoolField('active', 'active'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('countryArea', 'country_area_uuid', CountryAreaDefinition::class, false),
            new ManyToOneAssociationField('country', 'country_uuid', CountryDefinition::class, false),
            new ManyToOneAssociationField('countryState', 'country_state_uuid', CountryStateDefinition::class, false),
            new ManyToOneAssociationField('tax', 'tax_uuid', TaxDefinition::class, false),
            new ManyToOneAssociationField('customerGroup', 'customer_group_uuid', CustomerGroupDefinition::class, false),
            (new TranslationsAssociationField('translations', TaxAreaRuleTranslationDefinition::class, 'tax_area_rule_uuid', false, 'uuid'))->setFlags(new Required()),
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
