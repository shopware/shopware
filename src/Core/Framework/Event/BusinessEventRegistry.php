<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

class BusinessEventRegistry
{
    /**
     * @var string[]
     */
    private $classes = [];

    public function addClasses(array $classes): void
    {
        $this->classes = array_unique(array_merge($this->classes, $classes));
    }

    public function getClasses(): array
    {
        return $this->classes;
    }
}
