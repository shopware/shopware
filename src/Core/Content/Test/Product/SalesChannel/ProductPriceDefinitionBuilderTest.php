<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceDefinitionBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\ListingPrice;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\ListingPriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tax\TaxEntity;

class ProductPriceDefinitionBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    /**
     * @var ProductPriceDefinitionBuilder
     */
    private $priceDefinitionBuilder;

    /**
     * @var string
     */
    private $currencyId;

    public function setUp(): void
    {
        $this->salesChannelContext = $this->createSalesChannelContext();

        $this->priceDefinitionBuilder = new ProductPriceDefinitionBuilder();

        $this->currencyId = Uuid::randomHex();
        $this->getContainer()->get('currency.repository')->create([
            [
                'id' => $this->currencyId,
                'factor' => 0.8,
                'symbol' => 'T',
                'isoCode' => 'TTT',
                'position' => 3,
                'decimalPrecision' => 2,
                'shortName' => 'TE',
                'name' => 'Test',
            ],
        ], $this->salesChannelContext->getContext());
    }

    public function testBuildPriceDefinitionsWithoutContextRules(): void
    {
        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);
        $this->addTaxEntityToSalesChannel($this->salesChannelContext, $tax);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([new Price(Defaults::CURRENCY, 7, 10, false)]),
            'taxId' => $tax->getId(),
            'name' => 'test',
        ]);

        $definitions = $this->priceDefinitionBuilder->build($product, $this->salesChannelContext)->getPrices();
        static::assertSame(0, $definitions->count());
    }

    public function testBuildPriceDefinitionsWithContextRulesInDefaultCurrencyUsesFirstMatchingRule(): void
    {
        $ruleId = Uuid::randomHex();
        $ruleId2 = Uuid::randomHex();

        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);
        $this->addTaxEntityToSalesChannel($this->salesChannelContext, $tax);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([new Price(Defaults::CURRENCY, 7, 10, false)]),
            'taxId' => $tax->getId(),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 100, 100, false)]),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 21,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 50, 70, false)]),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 1,
                                'ruleId' => $ruleId2,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 30, 50, false)]),
                            ]
                        ),
                ]
            ),
        ]);

        $this->salesChannelContext->setRuleIds([$ruleId, $ruleId2]);
        $definitions = $this->priceDefinitionBuilder->build($product, $this->salesChannelContext)->getPrices();
        static::assertSame(2, $definitions->count());

        /** @var QuantityPriceDefinition $first */
        $first = $definitions->get(0);
        $this->assertPriceDefinition($first, 100, 20);

        /** @var QuantityPriceDefinition $second */
        $second = $definitions->get(1);
        $this->assertPriceDefinition($second, 70, 21);
    }

    public function testBuildPriceDefinitionsWithContextRulesUsesContextCurrency(): void
    {
        $ruleId = Uuid::randomHex();

        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);
        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([new Price(Defaults::CURRENCY, 7, 10, false)]),
            'taxId' => $tax->getId(),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 100, 100, false)]),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 21,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 50, 50, false)]),
                            ]
                        ),
                ]
            ),
        ]);

        $salesChannelContext = $this->createSalesChannelContext([SalesChannelContextService::CURRENCY_ID => $this->currencyId]);
        $this->addTaxEntityToSalesChannel($salesChannelContext, $tax);
        $salesChannelContext->setRuleIds([$ruleId]);
        $definitions = $this->priceDefinitionBuilder->build($product, $salesChannelContext)->getPrices();
        static::assertSame(2, $definitions->count());

        /** @var QuantityPriceDefinition $first */
        $first = $definitions->get(0);
        $this->assertPriceDefinition($first, 100 * 0.8, 20);

        /** @var QuantityPriceDefinition $second */
        $second = $definitions->get(1);
        $this->assertPriceDefinition($second, 50 * 0.8, 21);
    }

    public function testBuildPriceDefinitionsWithContextRulesConvertsToContextCurrency(): void
    {
        $ruleId = Uuid::randomHex();
        $ruleId2 = Uuid::randomHex();

        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([new Price(Defaults::CURRENCY, 7, 10, false)]),
            'taxId' => $tax->getId(),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 100, 100, false)]),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 21,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 40, 50, false)]),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 1,
                                'ruleId' => $ruleId2,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 30, 40, false)]),
                            ]
                        ),
                ]
            ),
        ]);

        $salesChannelContext = $this->createSalesChannelContext([SalesChannelContextService::CURRENCY_ID => $this->currencyId]);
        $this->addTaxEntityToSalesChannel($salesChannelContext, $tax);
        $salesChannelContext->setRuleIds([$ruleId, $ruleId2]);
        $definitions = $this->priceDefinitionBuilder->build($product, $salesChannelContext)->getPrices();
        static::assertSame(2, $definitions->count());

        /** @var QuantityPriceDefinition $first */
        $first = $definitions->get(0);
        $this->assertPriceDefinition($first, 80, 20);

        /** @var QuantityPriceDefinition $second */
        $second = $definitions->get(1);
        $this->assertPriceDefinition($second, 40, 21);
    }

    public function testBuildPriceDefinitionInDefaultCurrency(): void
    {
        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);
        $this->addTaxEntityToSalesChannel($this->salesChannelContext, $tax);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([new Price(Defaults::CURRENCY, 7, 10, false)]),
            'taxId' => $tax->getId(),
            'name' => 'test',
        ]);

        $definition = $this->priceDefinitionBuilder->build($product, $this->salesChannelContext)->getPrice();
        $this->assertPriceDefinition($definition, 10, 1);
    }

    public function testBuildPriceDefinitionInDifferentCurrency(): void
    {
        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);
        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([new Price(Defaults::CURRENCY, 7, 10, false)]),
            'taxId' => $tax->getId(),
            'name' => 'test',
        ]);

        $salesChannelContext = $this->createSalesChannelContext([SalesChannelContextService::CURRENCY_ID => $this->currencyId]);
        $this->addTaxEntityToSalesChannel($salesChannelContext, $tax);
        $definition = $this->priceDefinitionBuilder->build($product, $salesChannelContext)->getPrice();
        $this->assertPriceDefinition($definition, 8, 1);
    }

    public function testBuildPriceDefinitionInDefaultCurrencyWithNetTaxState(): void
    {
        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);
        $this->addTaxEntityToSalesChannel($this->salesChannelContext, $tax);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([new Price(Defaults::CURRENCY, 7, 10, false)]),
            'taxId' => $tax->getId(),
            'name' => 'test',
        ]);

        $this->salesChannelContext->setTaxState(CartPrice::TAX_STATE_NET);
        $definition = $this->priceDefinitionBuilder->build($product, $this->salesChannelContext)->getPrice();
        $this->assertPriceDefinition($definition, 7, 1);
    }

    public function testBuildListingPriceDefinitionWithListingPrices(): void
    {
        $ruleId = Uuid::randomHex();
        $ruleId2 = Uuid::randomHex();
        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);
        $this->addTaxEntityToSalesChannel($this->salesChannelContext, $tax);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([new Price(Defaults::CURRENCY, 7, 10, false)]),
            'taxId' => $tax->getId(),
            'name' => 'test',
            'listingPrices' => new ListingPriceCollection([
                (new ListingPrice())->assign([
                    'currencyId' => Defaults::CURRENCY,
                    'ruleId' => $ruleId,
                    'from' => new Price(Defaults::CURRENCY, 50, 50, false),
                    'to' => new Price(Defaults::CURRENCY, 100, 100, false),
                ]),
                (new ListingPrice())->assign([
                    'currencyId' => Defaults::CURRENCY,
                    'ruleId' => $ruleId2,
                    'from' => new Price(Defaults::CURRENCY, 40, 40, false),
                    'to' => new Price(Defaults::CURRENCY, 150, 150, false),
                ]),
            ]),
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 200, 200, false)]),
                            ]
                        ),
                ]
            ),
        ]);

        $this->salesChannelContext->setRuleIds([$ruleId]);

        $definition = $this->priceDefinitionBuilder->build($product, $this->salesChannelContext);

        $this->assertPriceDefinition($definition->getFrom(), 50, 1);
        $this->assertPriceDefinition($definition->getTo(), 100, 1);
    }

    public function testBuildListingPriceDefinitionWithPricesListingPrices(): void
    {
        $ruleId = Uuid::randomHex();
        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);
        $this->addTaxEntityToSalesChannel($this->salesChannelContext, $tax);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([new Price(Defaults::CURRENCY, 7, 10, false)]),
            'taxId' => $tax->getId(),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection(
                                    [new Price(Defaults::CURRENCY, 200, 200, false, new Price(Defaults::CURRENCY, 500, 500, false))]
                                ),
                            ]
                        ),
                ]
            ),
        ]);

        $this->salesChannelContext->setRuleIds([$ruleId]);

        $definition = $this->priceDefinitionBuilder->build($product, $this->salesChannelContext);

        static::assertSame($definition->getQuantityPrice()->getListPrice(), (float) 500);
        static::assertSame($definition->getPrices()->first()->getListPrice(), (float) 500);
    }

    public function testBuildListingPriceDefinitionWithPricesListingPricesWithDifferentCurrency(): void
    {
        $ruleId = Uuid::randomHex();
        $salesChannelContext = $this->createSalesChannelContext([SalesChannelContextService::CURRENCY_ID => $this->currencyId]);

        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);
        $this->addTaxEntityToSalesChannel($salesChannelContext, $tax);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([new Price(Defaults::CURRENCY, 7, 10, false)]),
            'taxId' => $tax->getId(),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([
                                    new Price(Defaults::CURRENCY, 200, 200, false, new Price(Defaults::CURRENCY, 500, 500, false)),
                                    new Price($this->currencyId, 300, 300, false, new Price($this->currencyId, 550, 550, false)),
                                ]),
                            ]
                        ),
                ]
            ),
        ]);

        $salesChannelContext->setRuleIds([$ruleId]);

        $definition = $this->priceDefinitionBuilder->build($product, $salesChannelContext);

        static::assertSame($definition->getQuantityPrice()->getListPrice(), (float) 550);
        static::assertSame($definition->getPrices()->first()->getListPrice(), (float) 550);
    }

    public function testBuildListingPriceDefinitionWithPricesListingPricesWithCalculatedCurrency(): void
    {
        $ruleId = Uuid::randomHex();
        $salesChannelContext = $this->createSalesChannelContext([SalesChannelContextService::CURRENCY_ID => $this->currencyId]);

        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);
        $this->addTaxEntityToSalesChannel($salesChannelContext, $tax);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([new Price(Defaults::CURRENCY, 7, 10, false)]),
            'taxId' => $tax->getId(),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([
                                    new Price(Defaults::CURRENCY, 200, 200, false, new Price(Defaults::CURRENCY, 500, 500, false)),
                                ]),
                            ]
                        ),
                ]
            ),
        ]);

        $salesChannelContext->setRuleIds([$ruleId]);

        $definition = $this->priceDefinitionBuilder->build($product, $salesChannelContext);

        // Factor of currency is 0.8 -> ListPrice of 500 should be calculated to 400
        static::assertSame($definition->getQuantityPrice()->getListPrice(), (float) 400);
        static::assertSame($definition->getPrices()->first()->getListPrice(), (float) 400);
    }

    public function testBuildListingPriceDefinitionWithPricesListingPricesWithQuantity(): void
    {
        $ruleId = Uuid::randomHex();
        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);
        $this->addTaxEntityToSalesChannel($this->salesChannelContext, $tax);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 100,
            'price' => new PriceCollection([new Price(Defaults::CURRENCY, 7, 10, false)]),
            'taxId' => $tax->getId(),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection(
                                    [new Price(Defaults::CURRENCY, 200, 200, false, new Price(Defaults::CURRENCY, 500, 500, false))]
                                ),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 21,
                                'quantityEnd' => 50,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection(
                                    [new Price(Defaults::CURRENCY, 150, 150, false, new Price(Defaults::CURRENCY, 400, 400, false))]
                                ),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 51,
                                'quantityEnd' => null,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection(
                                    [new Price(Defaults::CURRENCY, 100, 100, false, new Price(Defaults::CURRENCY, 300, 300, false))]
                                ),
                            ]
                        ),
                ]
            ),
        ]);

        $this->salesChannelContext->setRuleIds([$ruleId]);
        $definition = $this->priceDefinitionBuilder->build($product, $this->salesChannelContext, 53);

        static::assertSame($definition->getQuantityPrice()->getListPrice(), (float) 300);
    }

    public function testBuildListingPriceDefinitionWithPrices(): void
    {
        $ruleId = Uuid::randomHex();
        $ruleId2 = Uuid::randomHex();

        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);
        $this->addTaxEntityToSalesChannel($this->salesChannelContext, $tax);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([new Price(Defaults::CURRENCY, 7, 10, false)]),
            'taxId' => $tax->getId(),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 100, 100, false)]),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 21,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 40, 40, false)]),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 1,
                                'ruleId' => $ruleId2,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 30, 30, false)]),
                            ]
                        ),
                ]
            ),
        ]);

        $this->salesChannelContext->setRuleIds([$ruleId]);
        $definition = $this->priceDefinitionBuilder->build($product, $this->salesChannelContext)->getFrom();

        $this->assertPriceDefinition($definition, 40, 1);
    }

    public function testBuildListingPriceDefinitionWithSimplePrice(): void
    {
        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);
        $this->addTaxEntityToSalesChannel($this->salesChannelContext, $tax);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([new Price(Defaults::CURRENCY, 7, 10, false)]),
            'taxId' => $tax->getId(),
            'name' => 'test',
        ]);

        $definition = $this->priceDefinitionBuilder->build($product, $this->salesChannelContext)->getFrom();

        $this->assertPriceDefinition($definition, 10, 1);
    }

    public function testBuildListingPriceDefinitionWithDifferentCurrency(): void
    {
        $ruleId = Uuid::randomHex();
        $salesChannelContext = $this->createSalesChannelContext([SalesChannelContextService::CURRENCY_ID => $this->currencyId]);

        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);
        $this->addTaxEntityToSalesChannel($salesChannelContext, $tax);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([new Price(Defaults::CURRENCY, 7, 10, false)]),
            'taxId' => $tax->getId(),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([
                                    new Price(Defaults::CURRENCY, 100, 100, false),
                                    new Price($this->currencyId, 90, 90, false),
                                ]),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 21,
                                'quantityEnd' => 30,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([
                                    new Price(Defaults::CURRENCY, 50, 50, false),
                                    new Price($this->currencyId, 40, 40, false),
                                ]),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 31,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([
                                    new Price(Defaults::CURRENCY, 40, 40, false),
                                    new Price($this->currencyId, 30, 30, false),
                                ]),
                            ]
                        ),
                ]
            ),
        ]);

        $salesChannelContext->setRuleIds([$ruleId]);
        $definition = $this->priceDefinitionBuilder->build($product, $salesChannelContext);

        $this->assertPriceDefinition($definition->getFrom(), 30, 1);
        $this->assertPriceDefinition($definition->getTo(), 90, 1);
    }

    public function testBuildListingPriceDefinitionConvertsPriceToContextCurrency(): void
    {
        $ruleId = Uuid::randomHex();
        $ruleId2 = Uuid::randomHex();
        $salesChannelContext = $this->createSalesChannelContext([SalesChannelContextService::CURRENCY_ID => $this->currencyId]);

        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);
        $this->addTaxEntityToSalesChannel($salesChannelContext, $tax);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([new Price(Defaults::CURRENCY, 7, 7, false)]),
            'taxId' => $tax->getId(),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 100, 100, false)]),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 21,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 45, 45, false)]),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 1,
                                'ruleId' => $ruleId2,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 30, 30, false)]),
                            ]
                        ),
                ]
            ),
        ]);

        $salesChannelContext->setRuleIds([$ruleId]);

        $definition = $this->priceDefinitionBuilder->build($product, $salesChannelContext);

        $this->assertPriceDefinition($definition->getFrom(), 36, 1);
        $this->assertPriceDefinition($definition->getTo(), 80, 1);
    }

    public function testBuildListingPriceDefinitionConvertsSimplePriceToContextCurrency(): void
    {
        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([
                new Price(Defaults::CURRENCY, 7, 10, false),
            ]),
            'taxId' => $tax->getId(),
            'name' => 'test',
        ]);

        $salesChannelContext = $this->createSalesChannelContext([SalesChannelContextService::CURRENCY_ID => $this->currencyId]);
        $this->addTaxEntityToSalesChannel($salesChannelContext, $tax);

        $definition = $this->priceDefinitionBuilder->build($product, $salesChannelContext);
        $this->assertPriceDefinition($definition->getFrom(), 8, 1);
        $this->assertPriceDefinition($definition->getTo(), 8, 1);
    }

    public function testBuildingListingPriceFromPrice(): void
    {
        $ruleId = Uuid::randomHex();

        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);
        $this->addTaxEntityToSalesChannel($this->salesChannelContext, $tax);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([new Price(Defaults::CURRENCY, 10, 10, false)]),
            'taxId' => $tax->getId(),
            'name' => 'test',
        ]);

        $this->salesChannelContext->setRuleIds([$ruleId]);

        $definitions = $this->priceDefinitionBuilder->build($product, $this->salesChannelContext);

        $this->assertPriceDefinition($definitions->getFrom(), 10, 1);
        $this->assertPriceDefinition($definitions->getTo(), 10, 1);
    }

    public function testBuildingListingPriceFromPrices(): void
    {
        $ruleId = Uuid::randomHex();
        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);
        $this->addTaxEntityToSalesChannel($this->salesChannelContext, $tax);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([new Price(Defaults::CURRENCY, 7, 10, false)]),
            'taxId' => $tax->getId(),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 1,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 100, 100, false)]),
                            ]
                        ),
                ]
            ),
        ]);

        $this->salesChannelContext->setRuleIds([$ruleId]);

        $definitions = $this->priceDefinitionBuilder->build($product, $this->salesChannelContext);

        $this->assertPriceDefinition($definitions->getFrom(), 100, 1);
        $this->assertPriceDefinition($definitions->getTo(), 100, 1);
    }

    public function testBuildPriceDefinitionForQuantityWithDefaultCurrency(): void
    {
        $ruleId = Uuid::randomHex();
        $ruleId2 = Uuid::randomHex();

        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);
        $this->addTaxEntityToSalesChannel($this->salesChannelContext, $tax);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([new Price(Defaults::CURRENCY, 7, 10, false)]),
            'taxId' => $tax->getId(),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 100, 100, false)]),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 21,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 40, 50, false)]),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 1,
                                'ruleId' => $ruleId2,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 30, 40, false)]),
                            ]
                        ),
                ]
            ),
        ]);

        $this->salesChannelContext->setRuleIds([$ruleId]);

        $definition = $this->priceDefinitionBuilder->build($product, $this->salesChannelContext, 20)->getQuantityPrice();
        $this->assertPriceDefinition($definition, 100, 20);

        $definition = $this->priceDefinitionBuilder->build($product, $this->salesChannelContext, 21)->getQuantityPrice();
        $this->assertPriceDefinition($definition, 50, 21);
    }

    public function testBuildPriceDefinitionForQuantityWithDifferentCurrency(): void
    {
        $ruleId = Uuid::randomHex();
        $salesChannelContext = $this->createSalesChannelContext([SalesChannelContextService::CURRENCY_ID => $this->currencyId]);

        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);
        $this->addTaxEntityToSalesChannel($salesChannelContext, $tax);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([new Price(Defaults::CURRENCY, 7, 10, false)]),
            'taxId' => $tax->getId(),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([
                                    new Price(Defaults::CURRENCY, 100, 100, false),
                                    new Price($this->currencyId, 99, 99, false),
                                ]),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 21,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([
                                    new Price(Defaults::CURRENCY, 50, 50, false),
                                ]),
                            ]
                        ),
                ]
            ),
        ]);

        $salesChannelContext->setRuleIds([$ruleId]);

        $definition = $this->priceDefinitionBuilder->build($product, $salesChannelContext, 20)->getQuantityPrice();
        $this->assertPriceDefinition($definition, 99, 20);

        $definition = $this->priceDefinitionBuilder->build($product, $salesChannelContext, 22)->getQuantityPrice();
        $this->assertPriceDefinition($definition, 50 * 0.8, 22);
    }

    public function testBuildPriceDefinitionForQuantityConvertsPriceToContextCurrency(): void
    {
        $ruleId = Uuid::randomHex();
        $ruleId2 = Uuid::randomHex();
        $salesChannelContext = $this->createSalesChannelContext([SalesChannelContextService::CURRENCY_ID => $this->currencyId]);

        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);
        $this->addTaxEntityToSalesChannel($salesChannelContext, $tax);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([new Price(Defaults::CURRENCY, 7, 10, false)]),
            'taxId' => $tax->getId(),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 100, 100, false)]),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 21,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 40, 50, false)]),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 1,
                                'ruleId' => $ruleId2,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 30, 40, false)]),
                            ]
                        ),
                ]
            ),
        ]);

        $salesChannelContext->setRuleIds([$ruleId]);
        $definition = $this->priceDefinitionBuilder->build($product, $salesChannelContext, 20)->getQuantityPrice();

        $this->assertPriceDefinition($definition, 80, 20);
    }

    public function testBuildPriceDefinitionForQuantityWithSimplePriceAndDefaultCurrency(): void
    {
        $ruleId = Uuid::randomHex();

        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);
        $this->addTaxEntityToSalesChannel($this->salesChannelContext, $tax);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([new Price(Defaults::CURRENCY, 7, 10, false)]),
            'taxId' => $tax->getId(),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 1,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 100, 100, false)]),
                            ]
                        ),
                ]
            ),
        ]);

        $definition = $this->priceDefinitionBuilder->build($product, $this->salesChannelContext, 20)->getQuantityPrice();

        $this->assertPriceDefinition($definition, 10, 20);
    }

    public function testBuildPriceDefinitionForQuantityConvertsSimplePriceToContextCurrency(): void
    {
        $ruleId = Uuid::randomHex();

        $salesChannelContext = $this->createSalesChannelContext([SalesChannelContextService::CURRENCY_ID => $this->currencyId]);

        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);
        $this->addTaxEntityToSalesChannel($salesChannelContext, $tax);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([new Price(Defaults::CURRENCY, 7, 10, false)]),
            'taxId' => $tax->getId(),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'quantityStart' => 1,
                                'ruleId' => $ruleId,
                                'price' => new PriceCollection([new Price(Defaults::CURRENCY, 100, 100, false)]),
                            ]
                        ),
                ]
            ),
        ]);

        $definition = $this->priceDefinitionBuilder->build($product, $salesChannelContext, 20)->getQuantityPrice();

        $this->assertPriceDefinition($definition, 8, 20);
    }

    public function testBuildPriceDefinitionWithCurrencySpecificPrice(): void
    {
        $salesChannelContext = $this->createSalesChannelContext([SalesChannelContextService::CURRENCY_ID => $this->currencyId]);

        $tax = (new TaxEntity())->assign(['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 10]);
        $this->addTaxEntityToSalesChannel($salesChannelContext, $tax);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new PriceCollection([
                new Price(Defaults::CURRENCY, 7, 10, false),
                new Price($this->currencyId, 9, 12, false),
            ]),
            'taxId' => $tax->getId(),
            'name' => 'test',
        ]);

        $definition = $this->priceDefinitionBuilder->build($product, $salesChannelContext)->getPrice();
        $this->assertPriceDefinition($definition, 12, 1);

        $definition = $this->priceDefinitionBuilder->build($product, $salesChannelContext)->getFrom();
        $this->assertPriceDefinition($definition, 12, 1);

        $definition = $this->priceDefinitionBuilder->build($product, $salesChannelContext)->getTo();
        $this->assertPriceDefinition($definition, 12, 1);

        $definition = $this->priceDefinitionBuilder->build($product, $salesChannelContext)->getQuantityPrice();
        $this->assertPriceDefinition($definition, 12, 1);
    }

    private function createSalesChannelContext(array $options = []): SalesChannelContext
    {
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        $token = Uuid::randomHex();

        return $salesChannelContextFactory->create($token, Defaults::SALES_CHANNEL, $options);
    }

    private function assertPriceDefinition(QuantityPriceDefinition $definition, float $price, int $quantity): void
    {
        static::assertSame($price, $definition->getPrice());
        static::assertSame($quantity, $definition->getQuantity());
        static::assertTrue($definition->isCalculated());
    }
}
