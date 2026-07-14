<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-14 11:31:39
 */

namespace Shopware\Core\Framework\Api\Response;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
final readonly class SuccessResponse
{
    public function __construct(
        public ?bool $success = null,
    ) {
    }
}
