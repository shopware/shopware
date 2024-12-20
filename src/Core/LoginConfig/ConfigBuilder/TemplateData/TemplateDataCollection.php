<?php declare(strict_types=1);

namespace Shopware\Core\LoginConfig\ConfigBuilder\TemplateData;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @implements \Iterator<TemplateData>
 */
#[Package('core')]
class TemplateDataCollection implements \Iterator
{
    private int $index = 0;

    /**
     * @var array<TemplateData>
     */
    private array $providers = [];

    public function __construct()
    {
        $this->index = 0;
    }

    public function current(): TemplateData
    {
        return $this->providers[$this->index];
    }

    public function next(): void
    {
        ++$this->index;
    }

    public function key(): int
    {
        return $this->index;
    }

    public function valid(): bool
    {
        return isset($this->providers[$this->index]);
    }

    public function rewind(): void
    {
        $this->index = 0;
    }

    public function addTemplateData(TemplateData $provider): void
    {
        $this->providers[] = $provider;
    }
}
