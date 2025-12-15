<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer\CheapestPrice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceAccessorBuilder;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceField;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(CheapestPriceAccessorBuilder::class)]
class CheapestPriceAccessorBuilderTest extends TestCase
{
    protected CheapestPriceAccessorBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new CheapestPriceAccessorBuilder(
            1,
            new NullLogger()
        );
    }

    public function testWithPriceAccessor(): void
    {
        $priceField = new CheapestPriceField('cheapest_price_accessor', 'cheapest_price_accessor');
        $context = Context::createDefaultContext();

        $sql = $this->builder->buildAccessor('product', $priceField, $context, 'cheapestPrice');

        static::assertSame('COALESCE((ROUND((ROUND(CAST((JSON_UNQUOTE(JSON_EXTRACT(`product`.`cheapest_price_accessor`, "$.ruledefault.currencyb7d2554b0ce847cd82f3ac9bd1c0dfca.gross")) * 1) as DECIMAL(30, 20)), 2)) * 100, 0) / 100))', $sql);
    }

    public function testWithListPriceAccessor(): void
    {
        $priceField = new CheapestPriceField('cheapest_price_accessor', 'cheapest_price_accessor');
        $context = Context::createDefaultContext();

        $sql = $this->builder->buildAccessor('product', $priceField, $context, 'cheapestPrice.listPrice');

        static::assertSame('COALESCE((ROUND((ROUND(CAST((JSON_UNQUOTE(JSON_EXTRACT(`product`.`cheapest_price_accessor`, "$.ruledefault.currencyb7d2554b0ce847cd82f3ac9bd1c0dfca.listPrice.gross")) * 1) as DECIMAL(30, 20)), 2)) * 100, 0) / 100))', $sql);
    }

    public function testWithPercentageAccessor(): void
    {
        $priceField = new CheapestPriceField('cheapest_price_accessor', 'cheapest_price_accessor');
        $context = Context::createDefaultContext();

        $sql = $this->builder->buildAccessor('product', $priceField, $context, 'cheapestPrice.percentage');

        static::assertSame('COALESCE((ROUND((ROUND(CAST((JSON_UNQUOTE(JSON_EXTRACT(`product`.`cheapest_price_accessor`, "$.ruledefault.currencyb7d2554b0ce847cd82f3ac9bd1c0dfca.percentage.gross")) * 1) as DECIMAL(30, 20)), 2)) * 100, 0) / 100))', $sql);
    }

    public function testWithPercentageAccessorWithRule(): void
    {
        $priceField = new CheapestPriceField('cheapest_price_accessor', 'cheapest_price_accessor');
        $ruleId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $context->setRuleIds([
            $ruleId,
        ]);

        $sql = $this->builder->buildAccessor('product', $priceField, $context, 'cheapestPrice.percentage');

        static::assertSame('COALESCE((ROUND((ROUND(CAST((JSON_UNQUOTE(JSON_EXTRACT(`product`.`cheapest_price_accessor`, "$.rule' . $ruleId . '.currencyb7d2554b0ce847cd82f3ac9bd1c0dfca.percentage.gross")) * 1) as DECIMAL(30, 20)), 2)) * 100, 0) / 100),(ROUND((ROUND(CAST((JSON_UNQUOTE(JSON_EXTRACT(`product`.`cheapest_price_accessor`, "$.ruledefault.currencyb7d2554b0ce847cd82f3ac9bd1c0dfca.percentage.gross")) * 1) as DECIMAL(30, 20)), 2)) * 100, 0) / 100))', $sql);
    }

    public function testRuleLimit(): void
    {
        $priceField = new CheapestPriceField('cheapest_price_accessor', 'cheapest_price_accessor');
        $ruleId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $context->setRuleIds([
            $ruleId,
            Uuid::randomHex(),
        ]);

        $sql = $this->builder->buildAccessor('product', $priceField, $context, 'cheapestPrice.percentage');

        static::assertSame('COALESCE((ROUND((ROUND(CAST((JSON_UNQUOTE(JSON_EXTRACT(`product`.`cheapest_price_accessor`, "$.rule' . $ruleId . '.currencyb7d2554b0ce847cd82f3ac9bd1c0dfca.percentage.gross")) * 1) as DECIMAL(30, 20)), 2)) * 100, 0) / 100),(ROUND((ROUND(CAST((JSON_UNQUOTE(JSON_EXTRACT(`product`.`cheapest_price_accessor`, "$.ruledefault.currencyb7d2554b0ce847cd82f3ac9bd1c0dfca.percentage.gross")) * 1) as DECIMAL(30, 20)), 2)) * 100, 0) / 100))', $sql);
    }
}
