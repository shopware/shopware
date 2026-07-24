<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Template\Enum;

use Shopware\Core\Framework\Log\Package;

/**
 * UN/EDIFACT codelist 4461 — Payment means code.
 *
 * @see https://service.unece.org/trade/untdid/d16b/tred/tred4461.htm Authoritative UNTDID entry
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
enum PaymentMeansCode: string
{
    case CASH = '10';
    case CREDIT_TRANSFER = '30';
}
