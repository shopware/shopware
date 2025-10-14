<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\IdResolution\Parameter;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
final readonly class ResolutionParameter
{
    public function __construct(
        public string $name,
        public string $placeholder,
        public EntityResolution $resolution,
        public mixed $value
    ) {
    }
}
