<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
abstract class SingleFieldFilter extends Filter
{
    protected bool $isPrimary = false;

    protected ?string $resolved = null;

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
