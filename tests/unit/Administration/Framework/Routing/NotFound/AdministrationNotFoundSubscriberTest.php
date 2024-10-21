<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Framework\Routing\NotFound;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Framework\Routing\NotFound\AdministrationNotFoundSubscriber;
use Shopware\Core\Kernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(AdministrationNotFoundSubscriber::class)]
class AdministrationNotFoundSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertEquals(
            [
                KernelEvents::EXCEPTION => 'onError',
            ],
            AdministrationNotFoundSubscriber::getSubscribedEvents(),
        );
    }

    #[DataProvider('getInterceptingData')]
    public function testShowErrorPage(string $root, string $route): void
    {
        $container = new ContainerBuilder();
        $container->set('twig', $this->createMock(Environment::class));

        $subscriber = new AdministrationNotFoundSubscriber(
            $root,
            $container
        );

        $event = new ExceptionEvent(
            $this->createMock(Kernel::class),
            Request::create($route),
            0,
            new HttpException(Response::HTTP_NOT_FOUND)
        );

        $subscriber->onError($event);

        $response = $event->getResponse();

        static::assertInstanceOf(Response::class, $response);
    }

    #[DataProvider('getNonInterceptingData')]
    public function testDoNothingWhenNot404(string $route, \Exception $exception): void
    {
        $container = new ContainerBuilder();
        $container->set('twig', $this->createMock(Environment::class));

        $subscriber = new AdministrationNotFoundSubscriber(
            'admin',
            $container
        );

        $event = new ExceptionEvent(
            $this->createMock(Kernel::class),
            Request::create($route),
            0,
            $exception
        );

        $subscriber->onError($event);

        $response = $event->getResponse();

        static::assertNull($response);
    }

    /**
     * @return iterable<string, array<\Exception|string>>
     */
    public static function getNonInterceptingData(): iterable
    {
        yield 'valid admin route' => [
            'route' => '/admin',
            'exception' => new HttpException(Response::HTTP_OK),
        ];
        yield 'non-existing storefront route' => [
            'route' => '/foo',
            'exception' => new HttpException(Response::HTTP_NOT_FOUND),
        ];
        yield 'non-http exception' => [
            'route' => '/admin/foo',
            'exception' => new \Exception(),
        ];
    }

    /**
     * @return iterable<string, array<string>>
     */
    public static function getInterceptingData(): iterable
    {
        yield 'default admin route' => [
            'root' => 'admin',
            'route' => '/admin/foo',
        ];
        yield 'edited admin route' => [
            'root' => 'backend',
            'route' => '/backend/foo',
        ];
    }
}
