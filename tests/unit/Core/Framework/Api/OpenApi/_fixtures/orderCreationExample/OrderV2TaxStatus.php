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
 * Tax calculation mode defined by Shopware's cart price model.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
enum OrderV2TaxStatus: string
{
    case GROSS = 'gross';
    case NET = 'net';
    case TAX_FREE = 'tax-free';
}
