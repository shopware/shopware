<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\Request\AbstractRequest;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
final class CreateItemsRequest extends AbstractRequest
{
    /**
     * @var list<int> Optional scores
     */
    #[Assert\All(new Assert\Type('int'))]
    public array $scores;

    /**
     * @var list<bool> Boolean flags
     */
    #[Assert\Count(min: 1)]
    #[Assert\All(new Assert\Type('bool'))]
    public array $flags;

    /**
     * @var list<string> Non-blank strings
     */
    #[Assert\Count(min: 1)]
    #[Assert\All([new Assert\Type('string'), new Assert\Length(min: 1)])]
    public array $vatIds;

    /**
     * Untyped array
     *
     * @var array<string, mixed>
     */
    public array $untyped;

    /**
     * @internal
     */
    public function __construct(
        /**
         * @var list<string> List of tags
         */
        #[Assert\NotNull]
        #[Assert\Count(min: 1)]
        #[Assert\All(new Assert\Type('string'))]
        public array $tags,
        /**
         * @var list<string> List of UUIDs
         */
        #[Assert\NotNull]
        #[Assert\Count(min: 2)]
        #[Assert\All(new Assert\Type('string'))]
        public array $ids,
    ) {
    }
}
