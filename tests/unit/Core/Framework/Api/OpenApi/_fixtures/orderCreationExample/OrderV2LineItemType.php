<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-09-17 14:18:33
 */

namespace Shopware\Core\Checkout\Order\SalesChannel\Order;

use Shopware\Core\Framework\Log\Package;

/**
 * Line-item type accepted by Shopware's order line-item definition.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
enum OrderV2LineItemType: string
{
    case PRODUCT = 'product';
    case CREDIT = 'credit';
    case CUSTOM = 'custom';
    case PROMOTION = 'promotion';
    case CONTAINER = 'container';
    case DISCOUNT = 'discount';
    case QUANTITY = 'quantity';
}
