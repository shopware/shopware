<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Country\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
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
#[Package('buyers-experience')]
#[CoversClass(CountryRoute::class)]
class CountryRouteTest extends TestCase
{
    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $this->salesChannelContext = Generator::createSalesChannelContext(
            baseContext: new Context(new SalesChannelApiSource(Uuid::randomHex())),
            salesChannel: $salesChannel
        );
    }

    public function testLoad(): void
    {
        $index = 0;
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(static::exactly(2))
            ->method('dispatch')
            ->with(static::callback(static function ($event) use (&$index) {
                switch ($index) {
                    case 0:
                        ++$index;
                        static::assertInstanceOf(AddCacheTagEvent::class, $event);

                        return true;
                    case 1:
                        ++$index;
                        static::assertInstanceOf(CountryCriteriaEvent::class, $event);

                        return true;
                    default:
                        static::fail('Unexpected event dispatched');
                }
            }));

        $countryRepository = $this->createMock(SalesChannelRepository::class);
        $countryRepository->expects(static::once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'country',
                0,
                new CountryCollection(),
                null,
                new Criteria(),
                $this->salesChannelContext->getContext(),
            ));

        $route = new CountryRoute($countryRepository, $dispatcher);
        $route->load(new Request(), new Criteria(), $this->salesChannelContext);
    }
}
