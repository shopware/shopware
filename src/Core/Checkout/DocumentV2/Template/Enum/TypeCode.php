<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Template\Enum;

use Shopware\Core\Framework\Log\Package;

/**
 * UN/CEFACT codelist 1001 — Document name code.
 *
 * @see https://service.unece.org/trade/untdid/d16b/tred/tred1001.htm
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
enum TypeCode: int
{
    case INVOICE = 380;
    case CREDIT_NOTE = 381;
    case CANCELLATION_INVOICE = 384;
}
