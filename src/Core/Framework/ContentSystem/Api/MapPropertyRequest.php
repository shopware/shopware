<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 */
#[Package('framework')]
final class MapPropertyRequest
{
    /**
     * @param array<string, mixed> $source
     * @param array<int|string, mixed> $layout
     */
    public function __construct(
        public readonly string $elementId,
        public readonly string $propertyKey,
        #[Assert\Type('array')]
        public readonly array $source,
        #[Assert\NotBlank]
        public readonly string $rootSource,
        #[Assert\Type('array')]
        public readonly array $layout = [],
    ) {
    }
}
