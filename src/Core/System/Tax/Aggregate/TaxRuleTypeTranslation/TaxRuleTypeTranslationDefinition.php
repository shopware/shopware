<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxRuleTypeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeDefinition;

#[Package('customer-order')]
class TaxRuleTypeTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'tax_rule_type_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return TaxRuleTypeTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return TaxRuleTypeTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.1.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return TaxRuleTypeDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('type_name', 'typeName'))->addFlags(new Required()),
        ]);
    }
}
