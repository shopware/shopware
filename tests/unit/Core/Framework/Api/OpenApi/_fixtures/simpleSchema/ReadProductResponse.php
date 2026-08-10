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
 * Product found
 */
final readonly class ReadProductResponse implements StoreApiDTOResponseInterface
{
    public function __construct(
        public ?string $id = null,
        public ?string $name = null,
        public ?float $price = null,
    ) {
    }
}
