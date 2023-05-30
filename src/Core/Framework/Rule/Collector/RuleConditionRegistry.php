<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Collector;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\InvalidConditionException;
use Shopware\Core\Framework\Rule\FlowRule;
use Shopware\Core\Framework\Rule\Rule;

#[Package('business-ops')]
class RuleConditionRegistry
{
    /**
     * @var array<string, Rule>
     */
    private array $rules;

    /**
     * @internal
     *
     * @param iterable<Rule> $taggedRules
     */
    public function __construct(iterable $taggedRules)
    {
        $this->mapRules($taggedRules);
    }

    /**
     * @return array<string>
     */
    public function getNames(): array
    {
        return array_keys($this->rules);
    }

    public function has(string $name): bool
    {
        try {
            $this->getRuleInstance($name);
        } catch (InvalidConditionException) {
            return false;
        }

        return true;
    }

    public function getRuleInstance(string $name): Rule
    {
        if (!\array_key_exists($name, $this->rules)) {
            throw new InvalidConditionException($name);
        }

        return $this->rules[$name];
    }

    /**
     * @return class-string<Rule>
     */
    public function getRuleClass(string $name): string
    {
        return $this->getRuleInstance($name)::class;
    }

    /**
     * @return string[]
     */
    public function getFlowRuleNames(): array
    {
        $types = [];

        foreach ($this->rules as $rule) {
            if (is_subclass_of($rule, FlowRule::class)) {
                $types[] = $rule->getName();
            }
        }

        return $types;
    }

    /**
     * @param iterable<Rule> $taggedRules
     */
    private function mapRules(iterable $taggedRules): void
    {
        $this->rules = [];

        foreach ($taggedRules as $rule) {
            $this->rules[$rule->getName()] = $rule;
        }
    }
}
