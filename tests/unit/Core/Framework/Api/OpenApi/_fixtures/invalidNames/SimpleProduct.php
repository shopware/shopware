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
 * @codeCoverageIgnore
 */
final class SimpleProduct extends AbstractRequest
{
    public function __construct(
        public ?string $id = null,
        public ?string $name = null,
    ) {
    }
}
