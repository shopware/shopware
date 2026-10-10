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
 * Product found
 *
 * @codeCoverageIgnore
 */
final class ReadProductResponse extends AbstractResponse
{
    public string $id;

    public string $name;

    public float $price;

    /**
     * @internal
     */
    public function __construct(
    ) {
        parent::__construct();
    }
}
