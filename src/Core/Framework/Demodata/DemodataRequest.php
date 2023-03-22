<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata;

use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('core')]
class DemodataRequest
{
    /**
     * Number of entities indexed by definition
     *
     * @var array<string, int>
     */
    private array $numberOfItems = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $options = [];

    public function get(string $definition): int
    {
        return $this->numberOfItems[$definition] ?? 0;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function add(string $definition, int $numberOfItems, array $options = []): void
    {
        $this->numberOfItems[$definition] = $numberOfItems;
        $this->options[$definition] = $options;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(string $definition): array
    {
        return $this->options[$definition] ?? [];
    }

    /**
     * @return array<string, int>
     */
    public function all(): array
    {
        return $this->numberOfItems;
    }
}
