<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\Response\AbstractResponse;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Updated cart
 *
 * @codeCoverageIgnore
 */
#[JsonStreamable]
final class AddLineItemResponse extends AbstractResponse
{
    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $token,
        /**
         * @var list<LineItem>
         */
        #[Assert\Valid]
        public ?array $lineItems = null,
    ) {
    }
}
