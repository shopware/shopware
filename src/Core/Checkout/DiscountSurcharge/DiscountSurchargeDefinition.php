<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge;

use Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\DiscountSurchargeTranslationDefinition;
use Shopware\Core\Checkout\DiscountSurcharge\Collection\DiscountSurchargeBasicCollection;
use Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\DiscountSurchargeTranslationDefinition;
use Shopware\Core\Checkout\DiscountSurcharge\Struct\DiscountSurchargeBasicStruct;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\FloatField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ObjectField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;

class DiscountSurchargeDefinition extends EntityDefinition
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
        return 'discount_surcharge';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new Required()),
            (new FkField('rule_id', 'ruleId', RuleDefinition::class))->setFlags(new Required()),
            (new ObjectField('filter_rule', 'filterRule'))->setFlags(new Required()),
            (new StringField('type', 'type'))->setFlags(new Required()),
            (new FloatField('amount', 'amount'))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),

            (new TranslationsAssociationField('translations', DiscountSurchargeTranslationDefinition::class, 'discount_surcharge_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
            new ManyToOneAssociationField('rule', 'rule_id', RuleDefinition::class, true),
        ]);
    }

    public static function getBasicCollectionClass(): string
    {
        return DiscountSurchargeBasicCollection::class;
    }

    public static function getBasicStructClass(): string
    {
        return DiscountSurchargeBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return DiscountSurchargeTranslationDefinition::class;
    }
}
