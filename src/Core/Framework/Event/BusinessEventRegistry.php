<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
class BusinessEventRegistry
{
    /**
     * @var array<string>
     */
    private array $classes = [];

    public function addClasses(array $classes): void
    {
        $this->classes = array_unique(array_merge($this->classes, $classes));
    }

    public function getClasses(): array
    {
        return $this->classes;
    }
}
