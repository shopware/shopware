<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\IdResolution;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
final readonly class ResolutionParameterMap
{
    /**
     * @param array<string, ResolutionParameter> $map Parameter name => ResolutionParameter
     */
    public function __construct(
        private array $map = []
    ) {
        $this->validate();
    }

    public function isEmpty(): bool
    {
        return $this->map === [];
    }

    /**
     * Groups resolution parameters by entity type for batch processing.
     *
     * @return array<string, list<ResolutionParameter>>
     */
    public function groupByEntityType(): array
    {
        $grouped = [];

        foreach ($this->map as $item) {
            $entityType = $item->resolutionConfig->entity;

            $grouped[$entityType][] = $item;
        }

        return $grouped;
    }

    private function validate(): void
    {
        foreach ($this->map as $key => $value) {
            if (!\is_string($key)) {
                throw ContentSystemException::invalidMapKey('Resolution parameter map', get_debug_type($key));
            }

            if (!$value instanceof ResolutionParameter) {
                throw ContentSystemException::invalidMapValue('Resolution parameter map', $key, 'ResolutionParameter', get_debug_type($value));
            }
        }
    }
}
