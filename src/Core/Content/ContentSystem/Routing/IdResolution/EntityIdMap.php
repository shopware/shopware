<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\IdResolution;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class EntityIdMap
{
    /**
     * @param array<string, string> $map Placeholder name => Entity UUID
     */
    public function __construct(
        private readonly array $map = []
    ) {
        $this->validate();
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function get(string $placeholder): ?string
    {
        return $this->map[$placeholder] ?? null;
    }

    public function has(string $placeholder): bool
    {
        return isset($this->map[$placeholder]);
    }

    public function add(string $placeholder, string $entityId): self
    {
        $map = $this->map;
        $map[$placeholder] = $entityId;

        return new self($map);
    }

    public function merge(self $other): self
    {
        return new self(array_merge($this->map, $other->map));
    }

    public function isEmpty(): bool
    {
        return empty($this->map);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->map;
    }

    private function validate(): void
    {
        foreach ($this->map as $key => $value) {
            if (!\is_string($key)) {
                throw ContentSystemException::invalidMapKey('Entity ID map', get_debug_type($key));
            }

            if (!\is_string($value)) {
                throw ContentSystemException::invalidMapValue('Entity ID map', $key, 'string (UUID)', get_debug_type($value));
            }
        }
    }
}
