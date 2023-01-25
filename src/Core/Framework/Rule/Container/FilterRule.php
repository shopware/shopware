<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Container;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;

#[Package('business-ops')]
abstract class FilterRule extends Rule implements ContainerInterface
{
    /**
     * @var Container|null
     */
    protected $filter;

    public function addRule(Rule $rule): void
    {
        if ($this->filter === null) {
            $this->filter = new AndRule();
        }

        $this->filter->addRule($rule);
    }

    /**
     * @param Rule[] $rules
     */
    public function setRules(array $rules): void
    {
        $this->filter = new AndRule($rules);
    }

    /**
     * @return Rule[]
     */
    public function getRules(): array
    {
        return $this->filter ? $this->filter->getRules() : [];
    }
}
