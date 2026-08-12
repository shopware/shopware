<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-08-12 14:36:20
 */

namespace Shopware\Core\Framework\Api\Response\StoreApi;

use Shopware\Core\Framework\Api\Response\AbstractResponse;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class SuccessResponse extends AbstractResponse
{
    public function __construct(
        public ?bool $success = null,
    ) {
    }
}
