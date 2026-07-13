<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-13
 */

namespace Shopware\Core\Checkout\Customer\SalesChannel\Dto;

use Shopware\Core\Framework\Log\Package;

/**
 * Returns a success response indicating a successful update
 */
#[Package('framework')]
final readonly class ChangeProfileResponse
{
    public function __construct(
        public ?bool $success = null,
    ) {
    }
}
