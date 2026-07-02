<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\PagingListingProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(PagingListingProcessor::class)]
class PagingProcessorTest extends TestCase
{
    #[DataProvider('prepareProvider')]
    public function testPrepare(Request $request, Criteria $criteria, int $expectedOffset, int $expectedLimit): void
    {
        $context = $this->createMock(SalesChannelContext::class);

        $config = new StaticSystemConfigService([
            'core.listing.productsPerPage' => 24,
        ]);

        $processor = new PagingListingProcessor($config);
        $processor->prepare($request, $criteria, $context);

        static::assertSame($expectedOffset, $criteria->getOffset());
        static::assertSame($expectedLimit, $criteria->getLimit());
    }

    public static function prepareProvider(): \Generator
    {
        yield 'Provided limit will be accepted' => [
            new Request(),
            (new Criteria())->setLimit(10),
            0,
            10,
        ];

        yield 'Not provided limit' => [
            new Request(),
            (new Criteria())->setOffset(0),
            0,
            24,
        ];

        yield 'Provided page will be accepted' => [
            new Request(['p' => 2]),
            (new Criteria())->setOffset(24)->setLimit(24),
            24,
            24,
        ];

        yield 'Provided page and free limit will be accepted' => [
            new Request(['p' => 2]),
            (new Criteria())->setOffset(10)->setLimit(100),
            100,
            100,
        ];

        yield 'Test negative limit' => [
            new Request(),
            (new Criteria())->setOffset(0)->setLimit(-1),
            0,
            24,
        ];

        yield 'Test negative page' => [
            new Request(['p' => -1]),
            (new Criteria())->setOffset(0)->setLimit(24),
            0,
            24,
        ];
    }

    public function testProcess(): void
    {
        $request = new Request(['p' => 2]);
        $criteria = new Criteria();
        $criteria->setLimit(24);

        $result = new ProductListingResult('foo', 100, new ProductCollection(), null, $criteria, Context::createDefaultContext());
        $context = $this->createMock(SalesChannelContext::class);

        $config = new StaticSystemConfigService([
            'core.listing.productsPerPage' => 24,
        ]);

        $processor = new PagingListingProcessor($config);
        $processor->process($request, $result, $context);

        static::assertSame(2, $result->getPage());
        static::assertSame(24, $result->getLimit());
    }
}
