<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\Response\AbstractResponse;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Product detail
 *
 * @codeCoverageIgnore
 */
final class ReadProductResponse extends AbstractResponse
{
    #[Assert\Valid]
    public Product $product;

    /**
     * @internal
     */
    public function __construct(
    ) {
        parent::__construct();
    }
}
