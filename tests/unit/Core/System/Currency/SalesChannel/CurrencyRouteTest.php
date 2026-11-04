<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Currency\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\Extension\CurrencyRouteExtension;
use Shopware\Core\System\Currency\SalesChannel\CurrencyRoute;
use Shopware\Core\System\Currency\SalesChannel\CurrencyRouteResponse;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(CurrencyRoute::class)]
class CurrencyRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();
        $response = static::createStub(CurrencyRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('currency-route.load.pre', static function (CurrencyRouteExtension $extension) use ($request, $context, $criteria, $response): void {
            static::assertSame(['request' => $request, 'context' => $context, 'criteria' => $criteria], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new CurrencyRoute(
            static::createStub(SalesChannelRepository::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $context, $criteria));
    }
}
