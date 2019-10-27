<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxAreaRuleTypeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                  add(TaxAreaRuleTypeTranslationEntity $type)
 * @method TaxAreaRuleTypeTranslationEntity[]    getIterator()
 * @method TaxAreaRuleTypeTranslationEntity[]    getElements()
 * @method TaxAreaRuleTypeTranslationEntity|null get(string $key)
 * @method TaxAreaRuleTypeTranslationEntity|null first()
 * @method TaxAreaRuleTypeTranslationEntity|null last()
 */
class TaxAreaRuleTypeTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return TaxAreaRuleTypeTranslationEntity::class;
    }
}
