<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge;

use Shopware\Core\Checkout\DiscountSurcharge\Collection\DiscountSurchargeBasicCollection;
use Shopware\Core\Checkout\DiscountSurcharge\Collection\DiscountSurchargeDetailCollection;
use Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\DiscountSurchargeTranslationDefinition;
use Shopware\Core\Checkout\DiscountSurcharge\Event\DiscountSurchargeDeletedEvent;
use Shopware\Core\Checkout\DiscountSurcharge\Event\DiscountSurchargeWrittenEvent;

use Shopware\Core\Checkout\DiscountSurcharge\Struct\DiscountSurchargeBasicStruct;
use Shopware\Core\Checkout\DiscountSurcharge\Struct\DiscountSurchargeDetailStruct;
use Shopware\Core\Content\Rule\ContextRuleDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\FloatField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\JsonObjectField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\Serialized;

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
        return 'discount_surchage';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new Required()),
            (new FkField('context_rule_id', 'contextRuleId', ContextRuleDefinition::class))->setFlags(new Required()),
            (new JsonObjectField('rule', 'rule'))->setFlags(new Serialized(), new Required()),
            (new StringField('type', 'type'))->setFlags(new Required()),
            (new FloatField('amount', 'amount'))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),

            (new TranslationsAssociationField('translations', DiscountSurchargeTranslationDefinition::class, 'discount_surcharge_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
            new ManyToOneAssociationField('contextRule', 'context_rule_id', ContextRuleDefinition::class, true),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return DiscountSurchargeRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return DiscountSurchargeBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return DiscountSurchargeDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return DiscountSurchargeWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return DiscountSurchargeBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return DiscountSurchargeTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return DiscountSurchargeDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return DiscountSurchargeDetailCollection::class;
    }
}
