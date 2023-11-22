<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\TaxProvider\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Framework\App\TaxProvider\Response\TaxProviderResponse;
use Shopware\Core\Framework\Test\IdsCollection;

/**
 * @package checkout
 *
 * @internal
 */
#[CoversClass(TaxProviderResponse::class)]
class TaxProviderResponseTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testCreate(): void
    {
        $arrayStruct = [
            'lineItemTaxes' => [
                $this->ids->get('line-item-1') => [
                    [
                        'tax' => 1.0,
                        'taxRate' => 1.0,
                        'price' => 1.0,
                    ],
                ],
                $this->ids->get('line-item-2') => [
                    [
                        'tax' => 2.0,
                        'taxRate' => 2.0,
                        'price' => 2.0,
                    ],
                    [
                        'tax' => 3.0,
                        'taxRate' => 3.0,
                        'price' => 3.0,
                    ],
                ],
            ],
            'deliveryTaxes' => [
                $this->ids->get('delivery-1') => [
                    [
                        'tax' => 4.0,
                        'taxRate' => 4.0,
                        'price' => 4.0,
                    ],
                ],
                $this->ids->get('delivery-2') => [
                    [
                        'tax' => 5.0,
                        'taxRate' => 5.0,
                        'price' => 5.0,
                    ],
                    [
                        'tax' => 6.0,
                        'taxRate' => 6.0,
                        'price' => 6.0,
                    ],
                ],
            ],
            'cartPriceTaxes' => [
                [
                    'tax' => 7.0,
                    'taxRate' => 7.0,
                    'price' => 7.0,
                ],
                [
                    'tax' => 8.0,
                    'taxRate' => 8.0,
                    'price' => 8.0,
                ],
            ],
        ];

        $response = TaxProviderResponse::create($arrayStruct);

        static::assertNotNull($response->getLineItemTaxes());
        static::assertCount(2, $response->getLineItemTaxes());
        static::assertArrayHasKey($this->ids->get('line-item-1'), $response->getLineItemTaxes());
        static::assertArrayHasKey($this->ids->get('line-item-2'), $response->getLineItemTaxes());

        static::assertEquals(new CalculatedTax(1.0, 1.0, 1.0), $response->getLineItemTaxes()[$this->ids->get('line-item-1')]->getAt(0));
        static::assertEquals(new CalculatedTax(2.0, 2.0, 2.0), $response->getLineItemTaxes()[$this->ids->get('line-item-2')]->getAt(0));
        static::assertEquals(new CalculatedTax(3.0, 3.0, 3.0), $response->getLineItemTaxes()[$this->ids->get('line-item-2')]->getAt(1));

        static::assertNotNull($response->getDeliveryTaxes());
        static::assertCount(2, $response->getDeliveryTaxes());
        static::assertArrayHasKey($this->ids->get('delivery-1'), $response->getDeliveryTaxes());
        static::assertArrayHasKey($this->ids->get('delivery-2'), $response->getDeliveryTaxes());

        static::assertEquals(new CalculatedTax(4.0, 4.0, 4.0), $response->getDeliveryTaxes()[$this->ids->get('delivery-1')]->getAt(0));
        static::assertEquals(new CalculatedTax(5.0, 5.0, 5.0), $response->getDeliveryTaxes()[$this->ids->get('delivery-2')]->getAt(0));
        static::assertEquals(new CalculatedTax(6.0, 6.0, 6.0), $response->getDeliveryTaxes()[$this->ids->get('delivery-2')]->getAt(1));

        static::assertNotNull($response->getCartPriceTaxes());
        static::assertCount(2, $response->getCartPriceTaxes());
        static::assertEquals(new CalculatedTax(7.0, 7.0, 7.0), $response->getCartPriceTaxes()->getAt(0));
        static::assertEquals(new CalculatedTax(8.0, 8.0, 8.0), $response->getCartPriceTaxes()->getAt(1));
    }

    public function testSetters(): void
    {
        $response = new TaxProviderResponse();

        $response->setLineItemTaxes([
            $this->ids->get('line-item-1') => new CalculatedTaxCollection([
                new CalculatedTax(1.0, 1.0, 1.0),
            ]),
            $this->ids->get('line-item-2') => new CalculatedTaxCollection([
                new CalculatedTax(2.0, 2.0, 2.0),
                new CalculatedTax(3.0, 3.0, 3.0),
            ]),
        ]);

        $response->setDeliveryTaxes([
            $this->ids->get('delivery-1') => new CalculatedTaxCollection([
                new CalculatedTax(4.0, 4.0, 4.0),
            ]),
            $this->ids->get('delivery-2') => new CalculatedTaxCollection([
                new CalculatedTax(5.0, 5.0, 5.0),
                new CalculatedTax(6.0, 6.0, 6.0),
            ]),
        ]);

        $response->setCartPriceTaxes(new CalculatedTaxCollection([
            new CalculatedTax(7.0, 7.0, 7.0),
            new CalculatedTax(8.0, 8.0, 8.0),
        ]));

        static::assertNotNull($response->getLineItemTaxes());
        static::assertCount(2, $response->getLineItemTaxes());
        static::assertArrayHasKey($this->ids->get('line-item-1'), $response->getLineItemTaxes());
        static::assertArrayHasKey($this->ids->get('line-item-2'), $response->getLineItemTaxes());

        static::assertEquals(new CalculatedTax(1.0, 1.0, 1.0), $response->getLineItemTaxes()[$this->ids->get('line-item-1')]->getAt(0));
        static::assertEquals(new CalculatedTax(2.0, 2.0, 2.0), $response->getLineItemTaxes()[$this->ids->get('line-item-2')]->getAt(0));
        static::assertEquals(new CalculatedTax(3.0, 3.0, 3.0), $response->getLineItemTaxes()[$this->ids->get('line-item-2')]->getAt(1));

        static::assertNotNull($response->getDeliveryTaxes());
        static::assertCount(2, $response->getDeliveryTaxes());
        static::assertArrayHasKey($this->ids->get('delivery-1'), $response->getDeliveryTaxes());
        static::assertArrayHasKey($this->ids->get('delivery-2'), $response->getDeliveryTaxes());

        static::assertEquals(new CalculatedTax(4.0, 4.0, 4.0), $response->getDeliveryTaxes()[$this->ids->get('delivery-1')]->getAt(0));
        static::assertEquals(new CalculatedTax(5.0, 5.0, 5.0), $response->getDeliveryTaxes()[$this->ids->get('delivery-2')]->getAt(0));
        static::assertEquals(new CalculatedTax(6.0, 6.0, 6.0), $response->getDeliveryTaxes()[$this->ids->get('delivery-2')]->getAt(1));

        static::assertNotNull($response->getCartPriceTaxes());
        static::assertCount(2, $response->getCartPriceTaxes());
        static::assertEquals(new CalculatedTax(7.0, 7.0, 7.0), $response->getCartPriceTaxes()->getAt(0));
        static::assertEquals(new CalculatedTax(8.0, 8.0, 8.0), $response->getCartPriceTaxes()->getAt(1));
    }
}
