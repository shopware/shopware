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
 * Customer group of the current user
 *
 * @codeCoverageIgnore
 */
final class SalesChannelContextCurrentCustomerGroup extends AbstractRequest
{
    public function __construct(
        /**
         * Name of the group
         */
        public ?string $name = null,
        /**
         * Whether prices are displayed gross
         */
        public ?bool $displayGross = null,
    ) {
    }
}
