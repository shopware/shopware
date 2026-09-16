<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\AbstractDto;

/**
 * Country details
 *
 * @codeCoverageIgnore
 */
final class OrderBillingAddressCountry extends AbstractDto
{
    /**
     * ISO 3166-1 alpha-2 code
     */
    public string $iso;

    public string $name;

    /**
     * @internal
     */
    public function __construct(
    ) {
    }
}
