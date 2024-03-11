<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\SalesChannel\Listing\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\CompositeListingProcessor;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CompositeListingProcessor::class)]
class CompositeProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testComposition(): void
    {
        $request = new Request();
        $criteria = new Criteria();
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $request->query->set('no-aggregations', true);
        $this->getContainer()->get(CompositeListingProcessor::class)->prepare($request, $criteria, $context);
        static::assertEmpty($criteria->getAggregations());

        $request->query->set('only-aggregations', true);
        $this->getContainer()->get(CompositeListingProcessor::class)->prepare($request, $criteria, $context);
        static::assertEmpty($criteria->getSorting());
        static::assertEmpty($criteria->getAssociations());
        static::assertSame(0, $criteria->getLimit());
        static::assertSame(Criteria::TOTAL_COUNT_MODE_NONE, $criteria->getTotalCountMode());
    }
}
