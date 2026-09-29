<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilter;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilterFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductCloseoutFilterFactory::class)]
class ProductCloseoutFilterFactoryTest extends TestCase
{
    public function testCreatesProductCloseoutFilter(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $filter = (new ProductCloseoutFilterFactory())->create($context);

        static::assertEquals(new ProductCloseoutFilter(), $filter);
    }

    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        static::expectException(DecorationPatternException::class);
        (new ProductCloseoutFilterFactory())->getDecorated();
    }
}
