<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Text and client revision sent to the inline mapping parser action.
 *
 * @internal
 */
#[Package('framework')]
final class ContentInlineMappingParseRequest
{
    public function __construct(
        public readonly string $text,
        #[Assert\PositiveOrZero]
        public readonly int $revision,
    ) {
    }
}
