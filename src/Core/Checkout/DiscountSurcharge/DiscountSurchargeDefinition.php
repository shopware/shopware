<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge;

use Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\DiscountSurchargeTranslationDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class DiscountSurchargeDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'discount_surcharge';
    }

    public function getCollectionClass(): string
    {
        return DiscountSurchargeCollection::class;
    }

    public function getEntityClass(): string
    {
        return DiscountSurchargeEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new TranslatedField('name'),
            (new FkField('rule_id', 'ruleId', RuleDefinition::class))->addFlags(new Required()),
            (new StringField('type', 'type'))->addFlags(new Required()),
            (new FloatField('amount', 'amount'))->addFlags(new Required()),
            new TranslatedField('customFields'),
            (new TranslationsAssociationField(DiscountSurchargeTranslationDefinition::class, 'discount_surcharge_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('rule', 'rule_id', RuleDefinition::class, 'id', true),
        ]);
    }
}
