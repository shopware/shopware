<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Country\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\Event\CountryCriteriaEvent;
use Shopware\Core\System\Country\SalesChannel\CountryRoute;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(CountryRoute::class)]
class CountryRouteTest extends TestCase
{
    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $this->salesChannelContext = Generator::generateSalesChannelContext(
            baseContext: new Context(new SalesChannelApiSource(Uuid::randomHex())),
            salesChannel: $salesChannel
        );
    }

    public function testLoad(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->exactly(1))
            ->method('dispatch')
            ->with(static::isInstanceOf(CountryCriteriaEvent::class));

        $countryRepository = $this->createMock(SalesChannelRepository::class);
        $countryRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'country',
                0,
                new CountryCollection(),
                null,
                new Criteria(),
                $this->salesChannelContext->getContext(),
            ));

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);

        $route = new CountryRoute($countryRepository, $dispatcher, $cacheTagCollector);
        $route->load(new Request(), new Criteria(), $this->salesChannelContext);
    }
}
