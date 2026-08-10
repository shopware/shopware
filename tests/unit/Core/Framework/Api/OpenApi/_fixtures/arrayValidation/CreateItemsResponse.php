<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\Response\StoreApi\StoreApiDTOResponseInterface;

/**
 * Success
 */
final readonly class CreateItemsResponse implements StoreApiDTOResponseInterface
{
    public function __construct(
        public ?int $created = null,
    ) {
    }
}
