<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Collector;

use Shopware\Core\Framework\Rule\Exception\InvalidConditionException;
use Shopware\Core\Framework\Rule\Rule;

class RuleConditionRegistry
{
    /**
     * @var Rule[]
     */
    private $rules;

    public function __construct(iterable $taggedRules)
    {
        $this->mapRules($taggedRules);
    }

    /**
     * @return string[]
     */
    public function getNames(): array
    {
        return array_keys($this->rules);
    }

    public function has(string $name): bool
    {
        try {
            $this->getRuleInstance($name);
        } catch (InvalidConditionException $exception) {
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

    public function getRuleClass(string $name): string
    {
        return \get_class($this->getRuleInstance($name));
    }

    private function mapRules(iterable $taggedRules): void
    {
        $this->rules = [];

        /** @var Rule $rule */
        foreach ($taggedRules as $rule) {
            $this->rules[$rule->getName()] = $rule;
        }
    }
}
