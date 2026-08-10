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
 * API info
 *
 * @codeCoverageIgnore
 */
final readonly class ApiInfoResponse implements StoreApiDTOResponseInterface
{
    public function __construct(
        public ?string $version = null,
    ) {
    }
}
