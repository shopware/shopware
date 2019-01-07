<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Collector;

class ConditionCollector
{
    /**
     * @var iterable|RuleConditionCollectorInterface[]
     */
    private $taggedConditionCollectors;

    /**
     * @var array
     */
    private $classes;

    public function __construct(iterable $taggedConditionCollectors)
    {
        $this->taggedConditionCollectors = $taggedConditionCollectors;
    }

    /**
     * @return string[]
     */
    public function collect(): array
    {
        if ($this->classes) {
            return $this->classes;
        }

        $this->classes = [];
        foreach ($this->taggedConditionCollectors as $collector) {
            $this->classes = array_merge($this->classes, $collector->getClasses());
        }

        return $this->classes;
    }
}
