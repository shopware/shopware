<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\ProductPriceDefinitionBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Pricing\Price;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tax\TaxEntity;

class ProductPriceDefinitionBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;

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
                'position' => 3,
                'decimalPrecision' => 2,
                'shortName' => 'TE',
                'name' => 'Test',
            ],
        ], $this->salesChannelContext->getContext());
    }

    public function testBuildPriceDefinitionsWithoutContextRules()
    {
        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new Price(7, 10, false),
            'tax' => (new TaxEntity())->assign(['name' => 'test', 'taxRate' => 10]),
            'name' => 'test',
        ]);

        $definitions = $this->priceDefinitionBuilder->buildPriceDefinitions($product, $this->salesChannelContext);
        static::assertSame(0, $definitions->count());
    }

    public function testBuildPriceDefinitionsWithContextRulesInDefaultCurrencyUsesFirstMatchingRule()
    {
        $ruleId = Uuid::randomHex();
        $ruleId2 = Uuid::randomHex();

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new Price(7, 10, false),
            'tax' => (new TaxEntity())->assign(['name' => 'test', 'taxRate' => 10]),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new Price(100, 100, false),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 21,
                                'ruleId' => $ruleId,
                                'price' => new Price(50, 70, false),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 1,
                                'ruleId' => $ruleId2,
                                'price' => new Price(30, 50, false),
                            ]
                        ),
                ]
            ),
        ]);

        $this->salesChannelContext->setRuleIds([$ruleId, $ruleId2]);
        $definitions = $this->priceDefinitionBuilder->buildPriceDefinitions($product, $this->salesChannelContext);
        static::assertSame(2, $definitions->count());

        /** @var QuantityPriceDefinition $first */
        $first = $definitions->get(0);
        $this->assertPriceDefinition($first, 100, 20);

        /** @var QuantityPriceDefinition $second */
        $second = $definitions->get(1);
        $this->assertPriceDefinition($second, 70, 21);
    }

    public function testBuildPriceDefinitionsWithContextRulesUsesContextCurrency()
    {
        $ruleId = Uuid::randomHex();

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new Price(7, 10, false),
            'tax' => (new TaxEntity())->assign(['name' => 'test', 'taxRate' => 10]),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new Price(100, 100, false),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 21,
                                'ruleId' => $ruleId,
                                'price' => new Price(50, 70, false),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => $this->currencyId,
                                'quantityStart' => 1,
                                'ruleId' => $ruleId,
                                'price' => new Price(30, 50, false),
                            ]
                        ),
                ]
            ),
        ]);

        $salesChannelContext = $this->createSalesChannelContext([SalesChannelContextService::CURRENCY_ID => $this->currencyId]);
        $salesChannelContext->setRuleIds([$ruleId]);
        $definitions = $this->priceDefinitionBuilder->buildPriceDefinitions($product, $salesChannelContext);
        static::assertSame(1, $definitions->count());

        /** @var QuantityPriceDefinition $first */
        $first = $definitions->get(0);
        $this->assertPriceDefinition($first, 50, 1);
    }

    public function testBuildPriceDefinitionsWithContextRulesConvertsToContextCurrency()
    {
        $ruleId = Uuid::randomHex();
        $ruleId2 = Uuid::randomHex();

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new Price(7, 10, false),
            'tax' => (new TaxEntity())->assign(['name' => 'test', 'taxRate' => 10]),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new Price(100, 100, false),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 21,
                                'ruleId' => $ruleId,
                                'price' => new Price(40, 50, false),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 1,
                                'ruleId' => $ruleId2,
                                'price' => new Price(30, 40, false),
                            ]
                        ),
                ]
            ),
        ]);

        $salesChannelContext = $this->createSalesChannelContext([SalesChannelContextService::CURRENCY_ID => $this->currencyId]);
        $salesChannelContext->setRuleIds([$ruleId, $ruleId2]);
        $definitions = $this->priceDefinitionBuilder->buildPriceDefinitions($product, $salesChannelContext);
        static::assertSame(2, $definitions->count());

        /** @var QuantityPriceDefinition $first */
        $first = $definitions->get(0);
        $this->assertPriceDefinition($first, 80, 20);

        /** @var QuantityPriceDefinition $second */
        $second = $definitions->get(1);
        $this->assertPriceDefinition($second, 40, 21);
    }

    public function testBuildPriceDefinitionInDefaultCurrency()
    {
        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new Price(7, 10, false),
            'tax' => (new TaxEntity())->assign(['name' => 'test', 'taxRate' => 10]),
            'name' => 'test',
        ]);

        $definition = $this->priceDefinitionBuilder->buildPriceDefinition($product, $this->salesChannelContext);
        $this->assertPriceDefinition($definition, 10, 1);
    }

    public function testBuildPriceDefinitionInDifferentCurrency()
    {
        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new Price(7, 10, false),
            'tax' => (new TaxEntity())->assign(['name' => 'test', 'taxRate' => 10]),
            'name' => 'test',
        ]);

        $salesChannelContext = $this->createSalesChannelContext([SalesChannelContextService::CURRENCY_ID => $this->currencyId]);

        $definition = $this->priceDefinitionBuilder->buildPriceDefinition($product, $salesChannelContext);
        $this->assertPriceDefinition($definition, 8, 1);
    }

    public function testBuildPriceDefinitionInDefaultCurrencyWithNetTaxState()
    {
        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new Price(7, 10, false),
            'tax' => (new TaxEntity())->assign(['name' => 'test', 'taxRate' => 10]),
            'name' => 'test',
        ]);

        $this->salesChannelContext->setTaxState(CartPrice::TAX_STATE_NET);
        $definition = $this->priceDefinitionBuilder->buildPriceDefinition($product, $this->salesChannelContext);
        $this->assertPriceDefinition($definition, 7, 1);
    }

    public function testBuildListingPriceDefinitionWithListingPrices()
    {
        $ruleId = Uuid::randomHex();
        $ruleId2 = Uuid::randomHex();

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new Price(7, 10, false),
            'tax' => (new TaxEntity())->assign(['name' => 'test', 'taxRate' => 10]),
            'name' => 'test',
            'listingPrices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new Price(100, 100, false),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 21,
                                'ruleId' => $ruleId,
                                'price' => new Price(40, 50, false),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 1,
                                'ruleId' => $ruleId2,
                                'price' => new Price(30, 40, false),
                            ]
                        ),
                ]
            ),
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new Price(200, 200, false),
                            ]
                        ),
                ]
            ),
        ]);

        $definition = $this->priceDefinitionBuilder->buildListingPriceDefinition($product, $this->salesChannelContext);

        $this->assertPriceDefinition($definition, 100, 1);
    }

    public function testBuildListingPriceDefinitionWithPrices()
    {
        $ruleId = Uuid::randomHex();
        $ruleId2 = Uuid::randomHex();

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new Price(7, 10, false),
            'tax' => (new TaxEntity())->assign(['name' => 'test', 'taxRate' => 10]),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new Price(100, 100, false),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 21,
                                'ruleId' => $ruleId,
                                'price' => new Price(40, 50, false),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 1,
                                'ruleId' => $ruleId2,
                                'price' => new Price(30, 40, false),
                            ]
                        ),
                ]
            ),
        ]);

        $definition = $this->priceDefinitionBuilder->buildListingPriceDefinition($product, $this->salesChannelContext);

        $this->assertPriceDefinition($definition, 50, 1);
    }

    public function testBuildListingPriceDefinitionWithSimplePrice()
    {
        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new Price(7, 10, false),
            'tax' => (new TaxEntity())->assign(['name' => 'test', 'taxRate' => 10]),
            'name' => 'test',
        ]);

        $definition = $this->priceDefinitionBuilder->buildListingPriceDefinition($product, $this->salesChannelContext);

        $this->assertPriceDefinition($definition, 10, 1);
    }

    public function testBuildListingPriceDefinitionWithDifferentCurrency()
    {
        $ruleId = Uuid::randomHex();
        $salesChannelContext = $this->createSalesChannelContext([SalesChannelContextService::CURRENCY_ID => $this->currencyId]);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new Price(7, 10, false),
            'tax' => (new TaxEntity())->assign(['name' => 'test', 'taxRate' => 10]),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new Price(100, 100, false),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 21,
                                'ruleId' => $ruleId,
                                'price' => new Price(40, 50, false),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => $this->currencyId,
                                'quantityStart' => 1,
                                'ruleId' => $ruleId,
                                'price' => new Price(30, 40, false),
                            ]
                        ),
                ]
            ),
        ]);

        $definition = $this->priceDefinitionBuilder->buildListingPriceDefinition($product, $salesChannelContext);

        $this->assertPriceDefinition($definition, 40, 1);
    }

    public function testBuildListingPriceDefinitionConvertsPriceToContextCurrency()
    {
        $ruleId = Uuid::randomHex();
        $ruleId2 = Uuid::randomHex();
        $salesChannelContext = $this->createSalesChannelContext([SalesChannelContextService::CURRENCY_ID => $this->currencyId]);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new Price(7, 10, false),
            'tax' => (new TaxEntity())->assign(['name' => 'test', 'taxRate' => 10]),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new Price(100, 100, false),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 21,
                                'ruleId' => $ruleId,
                                'price' => new Price(45, 50, false),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 1,
                                'ruleId' => $ruleId2,
                                'price' => new Price(30, 45, false),
                            ]
                        ),
                ]
            ),
        ]);

        $definition = $this->priceDefinitionBuilder->buildListingPriceDefinition($product, $salesChannelContext);

        $this->assertPriceDefinition($definition, 40, 1);
    }

    public function testBuildListingPriceDefinitionConvertsSimplePriceToContextCurrency()
    {
        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new Price(7, 10, false),
            'tax' => (new TaxEntity())->assign(['name' => 'test', 'taxRate' => 10]),
            'name' => 'test',
        ]);

        $salesChannelContext = $this->createSalesChannelContext([SalesChannelContextService::CURRENCY_ID => $this->currencyId]);

        $definition = $this->priceDefinitionBuilder->buildListingPriceDefinition($product, $salesChannelContext);
        $this->assertPriceDefinition($definition, 8, 1);
    }

    public function testBuildListingPriceDefinitionsThrowsExceptionIfDefaultCurrencyRulesAreMissing()
    {
        static::expectException(\RuntimeException::class);

        $ruleId = Uuid::randomHex();

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new Price(7, 10, false),
            'tax' => (new TaxEntity())->assign(['name' => 'test', 'taxRate' => 10]),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => $this->currencyId,
                                'quantityStart' => 1,
                                'ruleId' => $ruleId,
                                'price' => new Price(100, 100, false),
                            ]
                        ),
                ]
            ),
        ]);

        $this->salesChannelContext->setRuleIds([$ruleId]);
        $this->priceDefinitionBuilder->buildListingPriceDefinition($product, $this->salesChannelContext);
    }

    public function testBuildPriceDefinitionForQuantityWithDefaultCurrency()
    {
        $ruleId = Uuid::randomHex();
        $ruleId2 = Uuid::randomHex();

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new Price(7, 10, false),
            'tax' => (new TaxEntity())->assign(['name' => 'test', 'taxRate' => 10]),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new Price(100, 100, false),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 21,
                                'ruleId' => $ruleId,
                                'price' => new Price(40, 50, false),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 1,
                                'ruleId' => $ruleId2,
                                'price' => new Price(30, 40, false),
                            ]
                        ),
                ]
            ),
        ]);

        $this->salesChannelContext->setRuleIds([$ruleId]);

        $definition = $this->priceDefinitionBuilder->buildPriceDefinitionForQuantity($product, $this->salesChannelContext, 20);
        $this->assertPriceDefinition($definition, 100, 20);

        $definition = $this->priceDefinitionBuilder->buildPriceDefinitionForQuantity($product, $this->salesChannelContext, 21);
        $this->assertPriceDefinition($definition, 50, 21);
    }

    public function testBuildPriceDefinitionForQuantityWithDifferentCurrency()
    {
        $ruleId = Uuid::randomHex();
        $salesChannelContext = $this->createSalesChannelContext([SalesChannelContextService::CURRENCY_ID => $this->currencyId]);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new Price(7, 10, false),
            'tax' => (new TaxEntity())->assign(['name' => 'test', 'taxRate' => 10]),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new Price(100, 100, false),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 21,
                                'ruleId' => $ruleId,
                                'price' => new Price(40, 50, false),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => $this->currencyId,
                                'quantityStart' => 1,
                                'ruleId' => $ruleId,
                                'price' => new Price(30, 40, false),
                            ]
                        ),
                ]
            ),
        ]);

        $salesChannelContext->setRuleIds([$ruleId]);
        $definition = $this->priceDefinitionBuilder->buildPriceDefinitionForQuantity($product, $salesChannelContext, 20);

        $this->assertPriceDefinition($definition, 40, 20);
    }

    public function testBuildPriceDefinitionForQuantityConvertsPriceToContextCurrency()
    {
        $ruleId = Uuid::randomHex();
        $ruleId2 = Uuid::randomHex();
        $salesChannelContext = $this->createSalesChannelContext([SalesChannelContextService::CURRENCY_ID => $this->currencyId]);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new Price(7, 10, false),
            'tax' => (new TaxEntity())->assign(['name' => 'test', 'taxRate' => 10]),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 1,
                                'quantityEnd' => 20,
                                'ruleId' => $ruleId,
                                'price' => new Price(100, 100, false),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 21,
                                'ruleId' => $ruleId,
                                'price' => new Price(40, 50, false),
                            ]
                        ),
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 1,
                                'ruleId' => $ruleId2,
                                'price' => new Price(30, 40, false),
                            ]
                        ),
                ]
            ),
        ]);

        $salesChannelContext->setRuleIds([$ruleId]);
        $definition = $this->priceDefinitionBuilder->buildPriceDefinitionForQuantity($product, $salesChannelContext, 20);

        $this->assertPriceDefinition($definition, 80, 20);
    }

    public function testBuildPriceDefinitionForQuantityWithSimplePriceAndDefaultCurrency()
    {
        $ruleId = Uuid::randomHex();

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new Price(7, 10, false),
            'tax' => (new TaxEntity())->assign(['name' => 'test', 'taxRate' => 10]),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 1,
                                'ruleId' => $ruleId,
                                'price' => new Price(100, 100, false),
                            ]
                        ),
                ]
            ),
        ]);

        $definition = $this->priceDefinitionBuilder->buildPriceDefinitionForQuantity($product, $this->salesChannelContext, 20);

        $this->assertPriceDefinition($definition, 10, 20);
    }

    public function testBuildPriceDefinitionForQuantityConvertsSimplePriceToContextCurrency()
    {
        $ruleId = Uuid::randomHex();

        $salesChannelContext = $this->createSalesChannelContext([SalesChannelContextService::CURRENCY_ID => $this->currencyId]);

        $product = (new ProductEntity())->assign([
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => new Price(7, 10, false),
            'tax' => (new TaxEntity())->assign(['name' => 'test', 'taxRate' => 10]),
            'name' => 'test',
            'prices' => new ProductPriceCollection(
                [
                    (new ProductPriceEntity())
                        ->assign(
                            [
                                'id' => Uuid::randomHex(),
                                'currencyId' => Defaults::CURRENCY,
                                'quantityStart' => 1,
                                'ruleId' => $ruleId,
                                'price' => new Price(100, 100, false),
                            ]
                        ),
                ]
            ),
        ]);

        $definition = $this->priceDefinitionBuilder->buildPriceDefinitionForQuantity($product, $salesChannelContext, 20);

        $this->assertPriceDefinition($definition, 8, 20);
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
