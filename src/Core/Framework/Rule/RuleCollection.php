<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Rule\Container\Container;
use Shopware\Core\Framework\Struct\Collection;

class RuleCollection extends Collection
{
    /**
     * @var Rule[]
     */
    protected $flat = [];

    /**
     * @var bool[]
     */
    protected $classes = [];

    /**
     * @param Rule $rule
     */
    public function add($rule): void
    {
        parent::add($rule);

        $this->addMeta($rule);
    }

    public function clear(): void
    {
        parent::clear();

        $this->flat = [];
        $this->classes = [];
    }

    public function filterInstance(string $class): RuleCollection
    {
        return new self(
            array_filter(
                $this->flat,
                function (Rule $rule) use ($class) {
                    return $rule instanceof $class;
                }
            )
        );
    }

    public function has($class): bool
    {
        return array_key_exists($class, $this->classes);
    }

    private function addMeta(Rule $rule): void
    {
        $this->classes[\get_class($rule)] = true;

        $this->flat[] = $rule;

        if ($rule instanceof Container) {
            foreach ($rule->getRules() as $childRule) {
                $this->addMeta($childRule);
            }
        }
    }
}
