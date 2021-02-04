<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

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
