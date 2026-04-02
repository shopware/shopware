<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\PriceFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;

/**
 * @internal
 */
#[CoversClass(PriceFieldAccessorBuilder::class)]
class PriceFieldAccessorBuilderTest extends TestCase
{
    private PriceFieldAccessorBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new PriceFieldAccessorBuilder(
            $this->createMock(Connection::class)
        );
    }

    public function testReturnsNullForNonPriceField(): void
    {
        $field = new StringField('name', 'name');
        $context = Context::createDefaultContext();

        static::assertNull($this->builder->buildAccessor('product', $field, $context, 'name'));
    }

    public function testPriceAccessorDoesNotInvert(): void
    {
        $field = new PriceField('price', 'price');
        $context = Context::createDefaultContext();

        $sql = $this->builder->buildAccessor('product', $field, $context, 'price');

        static::assertNotNull($sql);
        static::assertStringNotContainsString('100 -', $sql);
        static::assertStringContainsString('gross', $sql);
    }

    public function testListPriceAccessorDoesNotInvert(): void
    {
        $field = new PriceField('price', 'price');
        $context = Context::createDefaultContext();

        $sql = $this->builder->buildAccessor('product', $field, $context, 'price.listPrice');

        static::assertNotNull($sql);
        static::assertStringNotContainsString('100 -', $sql);
        static::assertStringContainsString('listPrice.gross', $sql);
    }

    public function testPercentageAccessorInvertsWithGrossContext(): void
    {
        $field = new PriceField('price', 'price');
        $context = Context::createDefaultContext();

        $sql = $this->builder->buildAccessor('product', $field, $context, 'price.percentage');

        static::assertNotNull($sql);
        static::assertStringStartsWith('(100 - ', $sql);
        static::assertStringContainsString('percentage.gross', $sql);
    }

    public function testPercentageAccessorInvertsWithNetContext(): void
    {
        $field = new PriceField('price', 'price');
        $context = Context::createDefaultContext();
        $context->setTaxState(CartPrice::TAX_STATE_NET);

        $sql = $this->builder->buildAccessor('product', $field, $context, 'price.percentage');

        static::assertNotNull($sql);
        static::assertStringStartsWith('(100 - ', $sql);
        static::assertStringContainsString('percentage.net', $sql);
        static::assertStringNotContainsString('percentage.gross', $sql);
    }

    public function testPercentageWithExplicitGrossAccessor(): void
    {
        $field = new PriceField('price', 'price');
        $context = Context::createDefaultContext();
        $context->setTaxState(CartPrice::TAX_STATE_NET);

        $sql = $this->builder->buildAccessor('product', $field, $context, 'price.percentage.gross');

        static::assertNotNull($sql);
        static::assertStringStartsWith('(100 - ', $sql);
        static::assertStringContainsString('percentage.gross', $sql);
    }

    public function testPercentageWithExplicitNetAccessor(): void
    {
        $field = new PriceField('price', 'price');
        $context = Context::createDefaultContext();

        $sql = $this->builder->buildAccessor('product', $field, $context, 'price.percentage.net');

        static::assertNotNull($sql);
        static::assertStringStartsWith('(100 - ', $sql);
        static::assertStringContainsString('percentage.net', $sql);
        static::assertStringNotContainsString('percentage.gross', $sql);
    }

    public function testPercentageAccessorUsesDefaultCurrency(): void
    {
        $field = new PriceField('price', 'price');
        $context = Context::createDefaultContext();

        $sql = $this->builder->buildAccessor('product', $field, $context, 'price.percentage');

        static::assertNotNull($sql);
        static::assertStringContainsString('$.c' . Defaults::CURRENCY . '.percentage.gross', $sql);
    }
}
