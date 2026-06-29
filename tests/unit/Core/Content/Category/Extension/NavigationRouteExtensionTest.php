<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Extension\NavigationRouteExtension;
use Shopware\Core\Content\Category\SalesChannel\NavigationRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Examples\NavigationRouteExample;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(NavigationRouteExtension::class)]
#[CoversClass(NavigationRouteExample::class)]
class NavigationRouteExtensionTest extends TestCase
{
    public function testSubscriberResolvesNavigation(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new NavigationRouteExample());

        $coreCalled = false;
        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: NavigationRouteExtension::NAME,
            extension: new NavigationRouteExtension(
                'active-id',
                'root-id',
                new Request(),
                $this->createMock(SalesChannelContext::class),
                new Criteria(),
            ),
            function: static function () use (&$coreCalled): NavigationRouteResponse {
                $coreCalled = true;

                return new NavigationRouteResponse(new CategoryCollection([
                    (new CategoryEntity())->assign(['id' => 'core-category']),
                ]));
            },
        );

        static::assertFalse($coreCalled, 'The core navigation loading must be skipped when a subscriber resolves it.');
        static::assertInstanceOf(NavigationRouteResponse::class, $result);
        static::assertSame(['example-category'], array_values($result->getCategories()->getIds()));
    }
}
