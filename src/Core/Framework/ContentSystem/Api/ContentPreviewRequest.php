<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request payload for the content-layout preview action. Validates the envelope only:
 * the layout stays a raw array so ContentElementFieldSerializer::decodeElement() remains
 * the single decode path.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class ContentPreviewRequest
{
    /**
     * @param array<int|string, mixed> $layout
     * @param array<string, mixed> $queryParameters
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Type('array')]
        public readonly array $layout,
        #[Assert\NotBlank]
        public readonly string $entityType,
        #[Assert\NotBlank]
        public readonly string $entityId,
        #[Assert\NotBlank]
        public readonly string $salesChannelId,
        public readonly ?string $languageId = null,
        public readonly ?string $currencyId = null,
        public readonly ?string $domainId = null,
        public readonly ?string $customerId = null,
        public readonly array $queryParameters = [],
    ) {
    }
}
