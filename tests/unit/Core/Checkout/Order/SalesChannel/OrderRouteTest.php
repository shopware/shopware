<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderRoute::class)]
class OrderRouteTest extends TestCase
{
    #[DataProvider('deeplinkFilterProvider')]
    public function testWronglyDeeplinkFilter(Filter $filter): void
    {
        $this->expectException(CustomerNotLoggedInException::class);

        $route = new OrderRoute(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(RateLimiter::class),
            $this->createMock(EventDispatcherInterface::class),
        );

        $route->load(new Request(), $this->createMock(SalesChannelContext::class), (new Criteria())->addFilter($filter));
    }

    /**
     * @return array<string, array{Filter}>
     */
    public static function deeplinkFilterProvider(): array
    {
        return [
            'deeplink equalsAny' => [new EqualsAnyFilter('deepLinkCode', ['deepLinkCode'])],
            'deeplink multi' => [new MultiFilter(MultiFilter::CONNECTION_OR, [new EqualsFilter('deepLinkCode', 'deepLinkCode')])],
            'deeplink not' => [new NotFilter(MultiFilter::CONNECTION_OR, [new EqualsFilter('deepLinkCode', 'deepLinkCode')])],
            'deeplink suffix' => [new SuffixFilter('deepLinkCode', 'Code')],
            'deeplink prefix' => [new PrefixFilter('deepLinkCode', 'deep')],
        ];
    }
}
