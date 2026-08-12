<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\Request\AbstractRequest;

/**
 * Country details
 *
 * @codeCoverageIgnore
 */
final class OrderBillingAddressCountry extends AbstractRequest
{
    public function __construct(
        /**
         * ISO 3166-1 alpha-2 code
         */
        public ?string $iso = null,
        public ?string $name = null,
    ) {
    }
}
