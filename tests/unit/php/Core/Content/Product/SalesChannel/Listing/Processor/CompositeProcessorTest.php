<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing\Processor;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\AbstractListingProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\CompositeProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\SalesChannel\Listing\Processor\CompositeProcessor
 */
class CompositeProcessorTest extends TestCase
{
    public function testPrepare(): void
    {
        $request = new Request();
        $criteria = new Criteria();
        $context = $this->createMock(SalesChannelContext::class);

        $processor = new CompositeProcessor([
            $dummy = new DummyProcessor(),
        ]);

        $processor->prepare($request, $criteria, $context);

        static::assertTrue($dummy->called);
    }

    public function testProcess(): void
    {
        $request = new Request();
        $result = $this->createMock(ProductListingResult::class);
        $context = $this->createMock(SalesChannelContext::class);

        $processor = new CompositeProcessor([
            $dummy = new DummyProcessor(),
        ]);

        $processor->process($request, $result, $context);

        static::assertTrue($dummy->called);
    }
}

/**
 * @internal
 */
class DummyProcessor extends AbstractListingProcessor
{
    public bool $called = false;

    public function getDecorated(): AbstractListingProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function prepare(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        $this->called = true;
    }

    public function process(Request $request, ProductListingResult $result, SalesChannelContext $context): void
    {
        $this->called = true;
    }
}
