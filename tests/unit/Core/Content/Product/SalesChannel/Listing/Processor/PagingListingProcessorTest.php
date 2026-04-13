<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\PagingListingProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(PagingListingProcessor::class)]
class PagingListingProcessorTest extends TestCase
{
    public static function provideTestPrepare(): \Generator
    {
        yield 'Empty criteria, empty request' => [
            'criteria' => new Criteria(),
            'request' => new Request(),
            'page' => 1,
            'limit' => 24,
        ];

        yield 'Empty criteria, request with page' => [
            'criteria' => new Criteria(),
            'request' => new Request(['p' => 2]),
            'page' => 2,
            'limit' => 24,
        ];

        yield 'Criteria with limit, empty request' => [
            'criteria' => (new Criteria())->setLimit(50),
            'request' => new Request(),
            'page' => 1,
            'limit' => 50,
        ];

        yield 'Criteria with limit, request with page' => [
            'criteria' => (new Criteria())->setLimit(50),
            'request' => new Request(['p' => 2]),
            'page' => 2,
            'limit' => 50,
        ];

        yield 'Criteria with limit & page, empty request' => [
            'criteria' => (new Criteria())->setLimit(50)->setOffset(50),
            'request' => new Request(),
            'page' => 2,
            'limit' => 50,
        ];

        yield 'Criteria with limit & page, request with page (should use request query parameter over criteria)' => [
            'criteria' => (new Criteria())->setLimit(50)->setOffset(200),
            'request' => new Request(['p' => 2]),
            'page' => 2,
            'limit' => 50,
        ];

        yield 'Criteria with limit & page, request with page (should use request body parameter over criteria)' => [
            'criteria' => (new Criteria())->setLimit(50)->setOffset(200),
            'request' => new Request(request: ['p' => 2]),
            'page' => 2,
            'limit' => 50,
        ];

        yield 'Criteria with limit & page, post request with page (should use request body parameter over query parameter and criteria)' => [
            'criteria' => (new Criteria())->setLimit(50)->setOffset(200),
            'request' => new Request(['p' => 2, 'limit' => 10], ['p' => 3, 'limit' => 25]),
            'page' => 3,
            'limit' => 25,
        ];

        yield 'Criteria with limit and max limit given' => [
            'criteria' => (new Criteria())->setLimit(200),
            'request' => new Request([]),
            'page' => 1,
            'limit' => 100,
        ];

        yield 'Empty criteria with limit and max limit given' => [
            'criteria' => new Criteria(),
            'request' => new Request([]),
            'page' => 1,
            'limit' => 10,
            'maxLimit' => 10,
        ];

        yield 'Empty criteria, request with page 0' => [
            'criteria' => new Criteria(),
            'request' => new Request(['p' => 0]),
            'page' => 1,
            'limit' => 24,
        ];

        yield 'Empty criteria, request with page -1' => [
            'criteria' => new Criteria(),
            'request' => new Request(['p' => -1]),
            'page' => 1,
            'limit' => 24,
        ];

        yield 'Empty criteria, request with limit given' => [
            'criteria' => new Criteria(),
            'request' => new Request(['p' => 1, 'limit' => 200]),
            'page' => 1,
            'limit' => 100,
        ];

        yield 'Empty criteria, request with limit and max limit given' => [
            'criteria' => new Criteria(),
            'request' => new Request(['p' => 1, 'limit' => 200]),
            'page' => 1,
            'limit' => 100,
        ];

        yield 'Criteria with limit & page, and request with limit' => [
            'criteria' => (new Criteria())->setLimit(50)->setOffset(50),
            'request' => new Request(['p' => 1, 'limit' => 200]),
            'page' => 1, // page should be taken from request
            'limit' => 200, // limit should be taken from request,
            'maxLimit' => 300,
        ];

        yield 'Request limit exceeds max limit - should be capped' => [
            'criteria' => new Criteria(),
            'request' => new Request(['limit' => 500]),
            'page' => 1,
            'limit' => 100, // should be capped to max limit
        ];

        yield 'Request limit within max limit - should be respected' => [
            'criteria' => new Criteria(),
            'request' => new Request(['limit' => 50]),
            'page' => 1,
            'limit' => 50,
        ];

        yield 'Explicit criteria limit (not from static config fallback) should be used' => [
            'criteria' => (new Criteria())->setLimit(30),
            'request' => new Request(),
            'page' => 1,
            'limit' => 30,
        ];

        yield 'Explicit criteria limit (not from static config fallback) respects max limit cap' => [
            'criteria' => (new Criteria())->setLimit(150),
            'request' => new Request(),
            'page' => 1,
            'limit' => 100, // explicit criteria limit (150) capped by max limit (100)
        ];

        yield 'Request body limit overrides query limit' => [
            'criteria' => new Criteria(),
            'request' => new Request(['limit' => 30], ['limit' => 40]),
            'page' => 1,
            'limit' => 40, // body parameter takes precedence
        ];

        yield 'Request body limit overrides query limit but respects max limit' => [
            'criteria' => new Criteria(),
            'request' => new Request(['limit' => 30], ['limit' => 200]),
            'page' => 1,
            'limit' => 100, // body parameter capped to max limit
        ];

        yield 'Zero or negative limit falls back to config' => [
            'criteria' => (new Criteria())->setLimit(0),
            'request' => new Request(),
            'page' => 1,
            'limit' => 24, // fallback to config value
        ];

        $criteriaWithState = (new Criteria())->setLimit(200);
        $criteriaWithState->addState(RequestCriteriaBuilder::STATE_NO_EXPLICIT_LIMIT_IN_REQUEST);

        yield 'Criteria limit from static config fallback - should use dynamic system config instead' => [
            'criteria' => clone $criteriaWithState,
            'request' => new Request(),
            'page' => 1,
            'limit' => 24, // uses dynamic system config (24), not criteria limit (200)
        ];

        yield 'Criteria limit from static config fallback - dynamic system config capped by max limit' => [
            'criteria' => clone $criteriaWithState,
            'request' => new Request(),
            'page' => 1,
            'limit' => 50, // dynamic system config (150) capped by max limit (50), not criteria limit (200)
            'maxLimit' => 50,
            'configLimit' => 150,
        ];

        yield 'Request limit has highest priority (even when criteria limit is from static config fallback)' => [
            'criteria' => clone $criteriaWithState,
            'request' => new Request(['limit' => 50]),
            'page' => 1,
            'limit' => 50, // explicit request limit (50) overrides both criteria limit (200) and system config
        ];

        yield 'Request limit has highest priority but is capped by max limit' => [
            'criteria' => clone $criteriaWithState,
            'request' => new Request(['limit' => 150]),
            'page' => 1,
            'limit' => 100, // explicit request limit (150) capped by max limit (100)
        ];
    }

    #[DataProvider('provideTestPrepare')]
    public function testPrepare(Criteria $criteria, Request $request, int $page, int $limit, int $maxLimit = PagingListingProcessor::DEFAULT_MAX_LIMIT, ?int $configLimit = null): void
    {
        $context = $this->createMock(SalesChannelContext::class);

        $processor = new PagingListingProcessor(
            new StaticSystemConfigService([
                'core.listing.productsPerPage' => $configLimit ?? PagingListingProcessor::DEFAULT_LIMIT,
            ]),
            $maxLimit
        );

        $processor->prepare($request, $criteria, $context);

        static::assertSame(($page - 1) * $limit, $criteria->getOffset());
        static::assertSame($limit, $criteria->getLimit());
    }

    public function testProcess(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);
        $request = new Request(['p' => 2]);
        $context = $this->createMock(SalesChannelContext::class);

        $processor = new PagingListingProcessor(
            new StaticSystemConfigService([
                'core.listing.productsPerPage' => 24,
            ])
        );

        $result = new ProductListingResult('product', 10, new ProductCollection(), new AggregationResultCollection(), $criteria, Context::createDefaultContext());

        $processor->process($request, $result, $context);

        static::assertSame(2, $result->getPage());
        static::assertSame(10, $result->getLimit());
    }
}
