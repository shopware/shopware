<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\Response\AbstractResponse;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Order created
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class CreateOrderResponse extends AbstractResponse
{
    #[Assert\Valid]
    public Order $order;

    /**
     * @internal
     */
    public function __construct(
    ) {
        parent::__construct();
    }
}
