<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxAreaRuleType;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                       add(TaxAreaRuleTypeEntity $type)
 * @method TaxAreaRuleTypeEntity[]    getIterator()
 * @method TaxAreaRuleTypeEntity[]    getElements()
 * @method TaxAreaRuleTypeEntity|null get(string $key)
 * @method TaxAreaRuleTypeEntity|null first()
 * @method TaxAreaRuleTypeEntity|null last()
 */
class TaxAreaRuleTypeCollection extends EntityCollection
{
    public function getByTechnicalName(string $technicalName): ?TaxAreaRuleTypeEntity
    {
        foreach ($this->getIterator() as $ruleTypeEntity) {
            if ($ruleTypeEntity->getTechnicalName() === $technicalName) {
                return $ruleTypeEntity;
            }
        }

        return null;
    }

    protected function getExpectedClass(): string
    {
        return TaxAreaRuleTypeEntity::class;
    }
}
