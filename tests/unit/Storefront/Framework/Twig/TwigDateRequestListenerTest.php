<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\Feature;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopware\Storefront\Framework\Twig\TwigDateRequestListener;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(TwigDateRequestListener::class)]
class TwigDateRequestListenerTest extends TestCase
{
    public static function dataProviderOnKernelRequest(): \Generator
    {
        yield [
            StorefrontRouteScope::ID,
            'UTC',
            false,
        ];

        yield [
            StorefrontRouteScope::ID,
            'Europe/Berlin',
            true,
        ];

        yield [
            'admin',
            'Europe/Berlin',
            false,
        ];

        yield [
            null,
            'Europe/Berlin',
            false,
        ];

        yield [
            null,
            'UTC',
            false,
        ];

        yield [
            StorefrontRouteScope::ID,
            null,
            false,
        ];

        yield [
            null,
            null,
            false,
        ];
    }

    #[DataProvider('dataProviderOnKernelRequest')]
    public function testEvent(?string $scope, ?string $cookie, bool $changed): void
    {
        $request = new Request();
        if ($scope) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [$scope]);
        }

        if ($cookie) {
            $request->cookies->set(TwigDateRequestListener::TIMEZONE_COOKIE, $cookie);
        }

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $container = new ContainerBuilder();
        $service = new Environment(new ArrayLoader());

        $beforeLocale = $service->getExtension(CoreExtension::class)->getTimezone();

        $container->set('twig', $service);
        $listener = new TwigDateRequestListener($container);

        $listener->__invoke($event);

        if ($changed) {
            static::assertNotSame(
                $beforeLocale,
                $service->getExtension(CoreExtension::class)->getTimezone()
            );
        } else {
            static::assertSame(
                $beforeLocale,
                $service->getExtension(CoreExtension::class)->getTimezone()
            );
        }
    }

    public function testListenerKeepsConfiguredTimezoneAsRenderFallback(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StorefrontRouteScope::ID]);
        $request->cookies->set(TwigDateRequestListener::TIMEZONE_COOKIE, 'America/New_York');

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $container = new ContainerBuilder();
        $twig = new TwigEnvironment(new ArrayLoader(['test' => '{{ testDate|date("Y-m-d") }}']));
        $twig->getExtension(CoreExtension::class)->setTimezone('Europe/Berlin');
        $container->set('twig', $twig);

        (new TwigDateRequestListener($container))->__invoke($event);

        static::assertSame('America/New_York', $twig->getExtension(CoreExtension::class)->getTimezone()->getName());

        Feature::fake(['v6.8.0.0'], function () use ($twig): void {
            static::assertSame('2026-01-02', $twig->renderWithTimezoneOverride('test', [
                'testDate' => new \DateTimeImmutable('2026-01-01 23:30:00', new \DateTimeZone('UTC')),
            ]));
        });
    }
}
