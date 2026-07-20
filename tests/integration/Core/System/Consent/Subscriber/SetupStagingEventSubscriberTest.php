<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Consent\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Shopware\Core\System\Consent\Subscriber\SetupStagingEventSubscriber;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('data-services')]
class SetupStagingEventSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testRemoveAllConsents(): void
    {
        $subscriber = $this->getContainer()->get(SetupStagingEventSubscriber::class);
        $connection = $this->getContainer()->get(Connection::class);

        $connection->executeStatement(
            'INSERT INTO `consent_state` (`id`, `name`, `identifier`, `state`, `actor`, `updated_at`)
                VALUES (:id, "Test Consent", "system", "system", "admin", "2026-02-04")',
            ['id' => Uuid::randomBytes()]
        );

        $connection->executeStatement(
            'INSERT INTO `consent_log` (`consent_name`, `timestamp`, `message`)
                VALUES ("Test Consent", "2026-02-04", "Consent given by admin")
        '
        );

        $subscriber->removeAllConsents(new SetupStagingEvent(
            Context::createCLIContext(),
            $this->createMock(SymfonyStyle::class),
        ));

        $count = $connection->executeQuery('SELECT count(*) FROM `consent_state`')->fetchOne();
        static::assertSame(0, (int) $count);

        $count = $connection->executeQuery('SELECT count(*) FROM `consent_log`')->fetchOne();
        static::assertSame(0, (int) $count);
    }
}
