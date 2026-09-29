<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Template\Enum;

use Shopware\Core\Framework\Log\Package;

/**
 * UN/ECE Recommendation 20 — unit of measure code.
 *
 * @see https://docs.peppol.eu/poacc/billing/3.0/codelist/UNECERec20
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
enum UnitCode: string
{
    case PIECE = 'H87';
}
