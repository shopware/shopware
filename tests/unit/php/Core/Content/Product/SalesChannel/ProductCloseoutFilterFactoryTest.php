<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilter;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilterFactory;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilterFactory
 */
class ProductCloseoutFilterFactoryTest extends TestCase
{
    use KernelTestBehaviour;

    public function testCreatesProductCloseoutFilter(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $filter = (new ProductCloseoutFilterFactory())->create($context);

        static::assertEquals(new ProductCloseoutFilter(), $filter);
    }

    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        static::expectException(DecorationPatternException::class);
        (new ProductCloseoutFilterFactory())->getDecorated();
    }
}
