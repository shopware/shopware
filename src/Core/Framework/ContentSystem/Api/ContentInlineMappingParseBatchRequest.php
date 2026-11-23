<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 */
#[Package('framework')]
final class ContentInlineMappingParseBatchRequest
{
    public const MAX_REQUESTS = 50;

    /**
     * @param list<ContentInlineMappingParseRequest> $requests
     */
    public function __construct(
        #[Assert\Type('list')]
        #[Assert\Count(min: 1, max: self::MAX_REQUESTS)]
        #[Assert\All([new Assert\Type(ContentInlineMappingParseRequest::class), new Assert\NotNull()])]
        #[Assert\Valid]
        public readonly array $requests,
    ) {
    }
}
