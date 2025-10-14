<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\IdResolution;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
final readonly class EntityIdMap
{
    /**
     * @param array<string, string> $map Placeholder name => Entity UUID
     */
    public function __construct(
        private array $map = []
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
