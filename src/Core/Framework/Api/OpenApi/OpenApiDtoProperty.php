<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OpenApi;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore Simple DTO with no logic.
 */
#[Package('framework')]
final readonly class OpenApiDtoProperty
{
    /**
     * @param list<string|int|float|bool>|null $enum
     */
    public function __construct(
        public string $name,
        public string $phpType,
        public bool $required,
        public bool $nullable,
        public ?string $arrayItemType = null,
        public ?string $description = null,
        public ?string $format = null,
        public ?string $pattern = null,
        public ?array $enum = null,
        public string|int|float|bool|null $defaultValue = null,
        public bool $hasDefaultValue = false,
        public ?int $minItems = null,
        public ?int $minLength = null,
        public ?int $arrayItemMinLength = null,
        public bool $unresolvedReference = false,
        public ?string $arrayMapValueType = null,
        public bool $nativeEnum = false,
    ) {
    }
}
