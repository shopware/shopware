<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
#[Package('core')]
abstract class SingleFieldFilter extends Filter
{
    /**
     * @var bool
     */
    protected $isPrimary;

    /**
     * @var string|null
     */
    protected $resolved;

    abstract public function getField(): string;

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): void
    {
        $this->isPrimary = $isPrimary;
    }

    public function getResolved(): ?string
    {
        return $this->resolved;
    }

    public function setResolved(string $resolved): void
    {
        $this->resolved = $resolved;
    }
}
