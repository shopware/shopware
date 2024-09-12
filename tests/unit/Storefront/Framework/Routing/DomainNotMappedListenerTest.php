<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Framework\Routing\DomainNotMappedListener;
use Shopware\Storefront\Framework\Routing\Exception\SalesChannelMappingException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(DomainNotMappedListener::class)]
class DomainNotMappedListenerTest extends TestCase
{
    public function testAnotherExceptionDoesNothing(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(static::never())->method('get');

        $listener = new DomainNotMappedListener($container);

        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            0,
            new \Exception()
        );

        $listener($event);
    }

    public function testSalesChannelMappingException(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(static::once())->method('get')->willReturn($this->createMock(Environment::class));

        $listener = new DomainNotMappedListener($container);

        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            0,
            new SalesChannelMappingException('test')
        );

        $listener($event);
    }
}
