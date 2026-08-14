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

/**
 * Returns context token
 *
 * @codeCoverageIgnore
 */
#[JsonStreamable]
final class ImitateCustomerLoginResponse extends AbstractResponse
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * Redirect URL if any
         */
        public ?string $redirectUrl = null,
    ) {
    }
}
