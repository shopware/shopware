<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-08-14 11:56:12
 */

namespace Shopware\Core\Framework\Api\Response\StoreApi;

use Shopware\Core\Framework\Api\Response\AbstractResponse;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[JsonStreamable]
final class SuccessResponse extends AbstractResponse
{
    /**
     * @internal
     */
    public function __construct(
        public ?bool $success = null,
    ) {
    }
}
