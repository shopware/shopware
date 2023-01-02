<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxRuleTypeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<TaxRuleTypeTranslationEntity>
 */
#[Package('customer-order')]
class TaxRuleTypeTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'tax_rule_type_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return TaxRuleTypeTranslationEntity::class;
    }
}
