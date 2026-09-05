<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\Request\AbstractRequest;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Imitate the log in as a customer given a generated token.
 *
 * @codeCoverageIgnore
 */
#[JsonStreamable]
final class LegacyImpersonationPayload extends AbstractRequest
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * Generated customer impersonation token (legacy UUID token).
         */
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $token,
        /**
         * ID of the customer.
         */
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $customerId,
        /**
         * ID of the user who generated the token.
         */
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $userId,
    ) {
    }
}
