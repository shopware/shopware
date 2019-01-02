<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Collector;

use Symfony\Component\EventDispatcher\Event;

class CollectConditionEvent extends Event
{
    const NAME = 'collect.conditions';

    /**
     * @var string[]
     */
    private $classes = [];

    public function addClasses(string ...$classes): void
    {
        $this->classes = array_merge($this->classes, $classes);
    }

    public function getClasses(): array
    {
        return $this->classes;
    }
}
