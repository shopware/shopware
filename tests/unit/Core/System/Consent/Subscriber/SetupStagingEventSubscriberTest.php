<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Shopware\Core\System\Consent\Subscriber\SetupStagingEventSubscriber;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(SetupStagingEventSubscriber::class)]
class SetupStagingEventSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertEquals([
            SetupStagingEvent::class => 'removeAllConsents',
        ], SetupStagingEventSubscriber::getSubscribedEvents());
    }

    public function testRemoveAllConsents(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))
            ->method('executeStatement');

        $ioMock = $this->createMock(SymfonyStyle::class);
        $ioMock->expects($this->once())
            ->method('info')
            ->with('All consents have been removed for staging setup.');

        $subscriber = new SetupStagingEventSubscriber($connection);

        $subscriber->removeAllConsents(new SetupStagingEvent(
            Context::createCLIContext(),
            $ioMock,
        ));
    }
}
