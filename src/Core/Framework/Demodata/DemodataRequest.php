<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata;

class DemodataRequest
{
    /**
     * Number of entities indexed by definition
     *
     * @var int[]
     */
    private $numberOfItems = [];

    /**
     * @var array
     */
    private $options = [];

    public function get(string $definition): int
    {
        return $this->numberOfItems[$definition] ?? 0;
    }

    public function add(string $definition, int $numberOfItems, array $options = []): void
    {
        $this->numberOfItems[$definition] = $numberOfItems;
        $this->options[$definition] = $options;
    }

    public function getOptions(string $definition): array
    {
        return $this->options[$definition] ?? [];
    }

    public function all(): array
    {
        return $this->numberOfItems;
    }
}
