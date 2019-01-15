<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Collector;

use Shopware\Core\Framework\Rule\InvalidConditionException;
use Shopware\Core\Framework\Rule\Rule;

class RuleConditionRegistry
{
    /**
     * @var iterable|RuleConditionCollectorInterface[]
     */
    private $taggedConditionCollectors;

    /**
     * @var array
     */
    private $names;

    public function __construct(iterable $taggedConditionCollectors)
    {
        $this->taggedConditionCollectors = $taggedConditionCollectors;
    }

    /**
     * @return string[]
     */
    public function collect(): array
    {
        if ($this->names) {
            return $this->names;
        }

        $classes = [];
        foreach ($this->taggedConditionCollectors as $collector) {
            $classes = array_merge($classes, $collector->getClasses());
        }

        /* @var Rule|string $class  */
        foreach ($classes as $class) {
            $this->names[$class] = $class::getName();
        }

        return $this->names;
    }

    public function has(string $name): bool
    {
        try {
            $this->getClass($name);
        } catch (InvalidConditionException $exception) {
            return false;
        }

        return true;
    }

    public function getClass(string $name): string
    {
        $classes = array_flip($this->collect());

        if (!array_key_exists($name, $classes)) {
            throw new InvalidConditionException($name);
        }

        return $classes[$name];
    }
}
