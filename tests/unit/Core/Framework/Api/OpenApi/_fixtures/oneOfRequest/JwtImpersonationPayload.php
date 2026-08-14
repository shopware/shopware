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
final class JwtImpersonationPayload extends AbstractRequest
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * Generated customer impersonation JWT token.
         */
        #[Assert\NotBlank]
        public string $token,
    ) {
    }
}
