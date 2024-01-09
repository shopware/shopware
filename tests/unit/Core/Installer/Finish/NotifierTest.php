<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Finish;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Finish\Notifier;
use Shopware\Core\Installer\Finish\UniqueIdGenerator;

/**
 * @internal
 */
#[CoversClass(Notifier::class)]
class NotifierTest extends TestCase
{
    public function testTrackEvent(): void
    {
        $idGenerator = $this->createMock(UniqueIdGenerator::class);
        $idGenerator->expects(static::once())->method('getUniqueId')
            ->willReturn('1234567890');

        $guzzleHandler = new MockHandler([new Response(200, [], \json_encode([
            'additionalData' => [
                'shopwareVersion' => '6.4.12',
                'language' => 'en',
            ],
            'instanceId' => '1234567890',
            'event' => Notifier::EVENT_INSTALL_FINISHED,
        ], \JSON_THROW_ON_ERROR))]);
        $client = new Client(['handler' => $guzzleHandler]);

        $notifier = new Notifier('http://localhost', $idGenerator, $client, '6.4.12');
        $notifier->doTrackEvent(Notifier::EVENT_INSTALL_FINISHED, ['language' => 'en']);

        $request = $guzzleHandler->getLastRequest();
        static::assertNotNull($request);

        $uri = $request->getUri();
        static::assertSame('http', $uri->getScheme());
        static::assertSame('localhost', $uri->getHost());
        static::assertSame('/swplatform/tracking/events', $uri->getPath());
    }

    public function testTrackEventDoesNotThrowOnException(): void
    {
        $idGenerator = $this->createMock(UniqueIdGenerator::class);
        $idGenerator->expects(static::once())->method('getUniqueId')
            ->willReturn('1234567890');

        $client = new Client([
            'handler' => MockHandler::createWithMiddleware([new Response(500)]),
        ]);

        $notifier = new Notifier('http://localhost', $idGenerator, $client, '6.4.12');
        $notifier->doTrackEvent(Notifier::EVENT_INSTALL_FINISHED, ['language' => 'en']);
    }
}
