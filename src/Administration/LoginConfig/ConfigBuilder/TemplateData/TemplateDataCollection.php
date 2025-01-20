<?php declare(strict_types=1);

namespace Shopware\Administration\LoginConfig\ConfigBuilder\TemplateData;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @implements \Iterator<ProviderTemplateData>
 */
#[Package('core')]
class TemplateDataCollection implements \Iterator, \JsonSerializable
{
    private int $index = 0;

    /**
     * @var array<ProviderTemplateData>
     */
    private array $providers = [];

    public function __construct()
    {
        $this->index = 0;
    }

    public function current(): ProviderTemplateData
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

    public function addTemplateData(ProviderTemplateData $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * @return array<ProviderTemplateData>
     */
    public function jsonSerialize(): array
    {
        return $this->providers;
    }
}
