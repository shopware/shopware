<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\TaxProvider\AbstractTaxProvider;
use Shopware\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
class TestEmptyTaxProvider extends AbstractTaxProvider
{
    public function provide(Cart $cart, SalesChannelContext $context): TaxProviderResult
    {
        $data = [
            'lineItemTaxes' => [
                'line-item-1' => new CalculatedTaxCollection(),
                'line-item-2' => new CalculatedTaxCollection(),
            ],
            'deliveryTaxes' => [
                'delivery-1' => new CalculatedTaxCollection(),
                'delivery-2' => new CalculatedTaxCollection(),
            ],
            'cartPriceTaxes' => new CalculatedTaxCollection(),
        ];

        /** @var TaxProviderResult $taxProviderStruct */
        $taxProviderStruct = TaxProviderResult::createFrom(new ArrayStruct($data));

        return $taxProviderStruct;
    }
}
