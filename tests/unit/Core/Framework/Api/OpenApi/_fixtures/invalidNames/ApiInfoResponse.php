<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\Response\AbstractResponse;

/**
 * API info
 *
 * @codeCoverageIgnore
 */
final class ApiInfoResponse extends AbstractResponse
{
    public function __construct(
        public ?string $version = null,
    ) {
    }
}
