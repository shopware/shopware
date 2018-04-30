<?php declare(strict_types=1);

namespace Shopware\Api\Context\Collection;

use Shopware\Api\Context\Struct\ContextPriceStruct;
use Shopware\Api\Entity\EntityCollection;
use Shopware\Context\Struct\ApplicationContext;

class ContextPriceCollection extends EntityCollection
{
    /**
     * @var ContextPriceStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ContextPriceStruct
    {
        return parent::get($id);
    }

    public function current(): ContextPriceStruct
    {
        return parent::current();
    }

    public function getCurrencyIds(): array
    {
        return $this->fmap(function (ContextPriceStruct $price) {
            return $price->getCurrencyId();
        });
    }

    public function filterByCurrencyId(string $id): self
    {
        return $this->filter(function (ContextPriceStruct $price) use ($id) {
            return $price->getCurrencyId() === $id;
        });
    }

    public function getContextRuleIds(): array
    {
        return $this->fmap(function (ContextPriceStruct $price) {
            return $price->getContextRuleId();
        });
    }

    public function filterByContextRuleId(string $id): self
    {
        return $this->filter(function (ContextPriceStruct $contextPrice) use ($id) {
            return $contextPrice->getContextRuleId() === $id;
        });
    }

    public function getPriceRulesForContext(ApplicationContext $context): ?self
    {
        foreach ($context->getContextRules() as $ruleId) {
            $rules = $this->filterByContextRuleId($ruleId);

            if ($rules->count() > 0) {
                return $rules;
            }
        }

        return null;
    }

    public function sortByPriceAscending(): void
    {
        $this->sort(function (ContextPriceStruct $a, ContextPriceStruct $b) {
            return $a->getPrice()->getGross() <=> $b->getPrice()->getGross();
        });
    }

    protected function getExpectedClass(): string
    {
        return ContextPriceStruct::class;
    }
}
