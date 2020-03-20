<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxRuleType;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                   add(TaxRuleTypeEntity $type)
 * @method TaxRuleTypeEntity[]    getIterator()
 * @method TaxRuleTypeEntity[]    getElements()
 * @method TaxRuleTypeEntity|null get(string $key)
 * @method TaxRuleTypeEntity|null first()
 * @method TaxRuleTypeEntity|null last()
 */
class TaxRuleTypeCollection extends EntityCollection
{
    public function getByTechnicalName(string $technicalName): ?TaxRuleTypeEntity
    {
        foreach ($this->getIterator() as $ruleTypeEntity) {
            if ($ruleTypeEntity->getTechnicalName() === $technicalName) {
                return $ruleTypeEntity;
            }
        }

        return null;
    }

    public function getApiAlias(): string
    {
        return 'tax_rule_type_collection';
    }

    protected function getExpectedClass(): string
    {
        return TaxRuleTypeEntity::class;
    }
}
