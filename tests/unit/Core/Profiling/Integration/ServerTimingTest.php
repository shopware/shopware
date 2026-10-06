<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Integration\ServerTiming;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ServerTiming::class)]
class ServerTimingTest extends TestCase
{
    public function testAddsStoppedEventToServerTimingHeader(): void
    {
        $serverTiming = new ServerTiming();
        $serverTiming->start('test::event', 'test', []);
        usleep(10000);
        $serverTiming->stop('test::event');

        $response = new Response();
        $serverTiming->onResponseEvent(new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        ));

        static::assertStringStartsWith('test.event;dur=', (string) $response->headers->get('Server-Timing'));
    }
}
