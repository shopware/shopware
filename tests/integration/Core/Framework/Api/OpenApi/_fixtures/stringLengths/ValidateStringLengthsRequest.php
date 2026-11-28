<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace Shopware\Core\Example\StringLengths;

use Shopware\Core\Framework\Api\Request\AbstractRequest;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
final class ValidateStringLengthsRequest extends AbstractRequest
{
    #[Assert\Length(min: 2, max: 4)]
    public string $bounded;

    #[Assert\Length(min: 2)]
    public string $minimumOnly;

    #[Assert\Length(max: 0)]
    public string $emptyOnly;

    #[Assert\Length(max: 4)]
    public ?string $nullable;

    /**
     * @var list<string>
     */
    #[Assert\All([new Assert\Type('string'), new Assert\Length(max: 4)])]
    public array $tags;

    /**
     * @var list<string>
     */
    #[Assert\All([new Assert\Type('string'), new Assert\Length(min: 2, max: 4)])]
    public array $boundedTags;

    /**
     * @var list<string>
     */
    #[Assert\All([new Assert\Type('string'), new Assert\Length(max: 0)])]
    public array $emptyTags;

    #[Assert\Length(max: 4)]
    public string $queryMaxOnly;

    #[Assert\Length(min: 2, max: 4)]
    public string $queryBounded;

    /**
     * @var list<string>
     */
    #[Assert\All([new Assert\Type('string'), new Assert\Length(max: 4)])]
    public array $queryTerms;

    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotNull]
        public string $unlimited,
        #[Assert\NotNull]
        #[Assert\Length(max: 4)]
        public string $maxOnly,
        #[Assert\NotNull]
        #[Assert\Length(max: 4)]
        public string $routeKey,
    ) {
    }
}
