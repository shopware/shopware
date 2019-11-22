<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxRuleTypeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                              add(TaxRuleTypeTranslationEntity $type)
 * @method TaxRuleTypeTranslationEntity[]    getIterator()
 * @method TaxRuleTypeTranslationEntity[]    getElements()
 * @method TaxRuleTypeTranslationEntity|null get(string $key)
 * @method TaxRuleTypeTranslationEntity|null first()
 * @method TaxRuleTypeTranslationEntity|null last()
 */
class TaxRuleTypeTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return TaxRuleTypeTranslationEntity::class;
    }
}
