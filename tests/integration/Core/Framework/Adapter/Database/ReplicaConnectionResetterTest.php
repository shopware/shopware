<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\ReplicaConnectionResetter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * @internal
 */
#[Package('framework')]
class ReplicaConnectionResetterTest extends TestCase
{
    use KernelTestBehaviour;

    public function testSubscriberIsRegisteredInTheCompiledContainer(): void
    {
        $dispatcher = static::getContainer()->get('event_dispatcher');
        \assert($dispatcher instanceof EventDispatcherInterface);

        static::assertTrue(
            $this->hasListener($dispatcher, KernelEvents::REQUEST, 'onKernelRequest'),
            'ReplicaConnectionResetter is not registered for kernel.request - was the service removed from the container?'
        );
        static::assertTrue(
            $this->hasListener($dispatcher, WorkerMessageReceivedEvent::class, 'reset'),
            'ReplicaConnectionResetter is not registered for WorkerMessageReceivedEvent - was the service removed from the container?'
        );
    }

    public function testTheSubscriberIsInstantiatedThroughTheContainerAndRuns(): void
    {
        $dispatcher = static::getContainer()->get('event_dispatcher');
        \assert($dispatcher instanceof EventDispatcherInterface);

        // getListeners() instantiates the subscriber through the real container
        $listener = null;
        foreach ($dispatcher->getListeners(KernelEvents::REQUEST) as $candidate) {
            if (\is_array($candidate) && $candidate[0] instanceof ReplicaConnectionResetter) {
                $listener = $candidate;
            }
        }

        static::assertNotNull($listener, 'ReplicaConnectionResetter could not be resolved from the event dispatcher');
        static::assertIsCallable($listener);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $listener(new RequestEvent($kernel, new Request(), HttpKernelInterface::MAIN_REQUEST));

        $dispatcher->dispatch(new WorkerMessageReceivedEvent(new Envelope(new \stdClass()), 'test-receiver'));

        static::assertSame('1', (string) static::getContainer()->get(Connection::class)->fetchOne('SELECT 1'));
    }

    private function hasListener(EventDispatcherInterface $dispatcher, string $eventName, string $method): bool
    {
        foreach ($dispatcher->getListeners($eventName) as $listener) {
            if (\is_array($listener) && $listener[0] instanceof ReplicaConnectionResetter && $listener[1] === $method) {
                return true;
            }
        }

        return false;
    }
}
