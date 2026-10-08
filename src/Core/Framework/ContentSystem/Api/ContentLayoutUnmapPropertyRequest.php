<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class ContentLayoutUnmapPropertyRequest
{
    public function __construct(
        public readonly string $elementId,
        public readonly string $propertyKey,
        public readonly ?string $expectedVersion,
    ) {
    }
}
