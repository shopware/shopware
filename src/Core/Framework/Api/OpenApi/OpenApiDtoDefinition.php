<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OpenApi;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

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
     * @param list<string|int|float|bool> $enumValues
     */
    public function __construct(
        public string $name,
        public array $properties,
        public ?string $description = null,
        public ?string $package = null,
        public array $enumValues = [],
        public ?string $enumType = null,
        public int $responseStatusCode = Response::HTTP_OK,
        public OpenApiDtoType $type = OpenApiDtoType::Nested,
    ) {
    }
}
