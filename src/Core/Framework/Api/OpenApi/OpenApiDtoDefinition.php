<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OpenApi;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore Simple DTO with no logic.
 */
#[Package('framework')]
final readonly class OpenApiDtoDefinition
{
    /**
     * @param list<OpenApiDtoProperty> $properties
     */
    public function __construct(
        public string $name,
        public array $properties,
        public ?string $description = null,
        public ?string $package = null,
    ) {
    }
}
