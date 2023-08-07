<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Finish;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Finish\Notifier;
use Shopware\Core\Installer\Finish\UniqueIdGenerator;

/**
 * @internal
 *
 * @covers \Shopware\Core\Installer\Finish\Notifier
 */
class NotifierTest extends TestCase
{
    public function testTrackEvent(): void
    {
        $idGenerator = $this->createMock(UniqueIdGenerator::class);
        $idGenerator->expects(static::once())->method('getUniqueId')
            ->willReturn('1234567890');

        $guzzle = $this->createMock(Client::class);
        $guzzle->expects(static::once())->method('postAsync')
            ->with(
                'http://localhost/swplatform/tracking/events',
                [
                    'json' => [
                        'additionalData' => [
                            'shopwareVersion' => '6.4.12',
                            'language' => 'en',
                        ],
                        'instanceId' => '1234567890',
                        'event' => Notifier::EVENT_INSTALL_FINISHED,
                    ],
                ]
            );

        $notifier = new Notifier('http://localhost', $idGenerator, $guzzle, '6.4.12');
        $notifier->doTrackEvent(Notifier::EVENT_INSTALL_FINISHED, ['language' => 'en']);
    }

    public function testTrackEventDoesNotThrowOnException(): void
    {
        $idGenerator = $this->createMock(UniqueIdGenerator::class);
        $idGenerator->expects(static::once())->method('getUniqueId')
            ->willReturn('1234567890');

        $guzzle = $this->createMock(Client::class);
        $guzzle->expects(static::once())->method('postAsync')
            ->willThrowException(new \Exception());

        $notifier = new Notifier('http://localhost', $idGenerator, $guzzle, '6.4.12');
        $notifier->doTrackEvent(Notifier::EVENT_INSTALL_FINISHED, ['language' => 'en']);
    }
}
