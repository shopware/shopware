<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Finish;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Finish\Notifier;

/**
 * @internal
 * @covers \Shopware\Core\Installer\Finish\Notifier
 */
class NotifierTest extends TestCase
{
    public function testTrackEvent(): void
    {
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
                        'instanceId' => 'unique',
                        'event' => Notifier::EVENT_INSTALL_FINISHED,
                    ],
                ]
            );

        $notifier = new Notifier('http://localhost', 'unique', $guzzle, '6.4.12');
        $notifier->doTrackEvent(Notifier::EVENT_INSTALL_FINISHED, ['language' => 'en']);
    }

    public function testTrackEventDoesNotThrowOnException(): void
    {
        $guzzle = $this->createMock(Client::class);
        $guzzle->expects(static::once())->method('postAsync')
            ->willThrowException(new \Exception());

        $notifier = new Notifier('http://localhost', 'unique', $guzzle, '6.4.12');
        $notifier->doTrackEvent(Notifier::EVENT_INSTALL_FINISHED, ['language' => 'en']);
    }
}
