<?php declare(strict_types=1);

namespace Acme\AcmePlugin\Tax;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\TaxProvider\AbstractTaxProvider;
use Shopware\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Acme tax provider — registered at priority 200 so it runs after Shopware's
 * built-in tax calculation. Returns null (pass-through) in this integration,
 * deferring all tax decisions to the default provider.
 *
 * Visible via: bin/console debug:container --tag shopware.tax.provider
 */
class AcmeTaxProvider extends AbstractTaxProvider
{
    public function provide(Cart $cart, SalesChannelContext $context): TaxProviderResult
    {
        return new TaxProviderResult([], []);
    }
}
