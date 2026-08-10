<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-08-10 11:16:52
 */

namespace Shopware\Core\Framework\Api\Response\StoreApi;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
final readonly class SuccessResponse implements StoreApiDTOResponseInterface
{
    public function __construct(
        public ?bool $success = null,
    ) {
    }
}
