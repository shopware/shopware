<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\IdResolution\Parameter;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
readonly class ResolutionParameter
{
    public function __construct(
        public string $name,
        public string $placeholder,
        public EntityResolution $resolution,
        public mixed $value
    ) {
    }

    public function getEntityType(): string
    {
        return $this->resolution->entityType;
    }

    public function getMatchField(): string
    {
        return $this->resolution->matchField;
    }

    public function hasConstraints(): bool
    {
        return $this->resolution->hasConstraints();
    }
}
