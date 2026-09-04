<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 */
#[Package('framework')]
final class InsertPresetRequest
{
    /**
     * @param array<int|string, mixed> $layout
     */
    public function __construct(
        public readonly string $presetId,
        #[Assert\Type('array')]
        public readonly array $layout = [],
        public readonly ?string $parentElementId = null,
        public readonly ?string $slot = null,
        public readonly ?string $rootSource = null,
    ) {
    }
}
