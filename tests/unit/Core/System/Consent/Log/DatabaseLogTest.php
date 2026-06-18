<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\Log;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\Log\DatabaseLog;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(DatabaseLog::class)]
class DatabaseLogTest extends TestCase
{
    public function testWritesToDB(): void
    {
        $clock = new MockClock('2026-05-01 12:00:00');

        $connection = $this->createMock(Connection::class);

        $connection->method('insert')
            ->with(
                'consent_log',
                static::callback(function (array $data) use ($clock) {
                    static::assertSame([
                        'consent_name' => 'test-consent',
                        'timestamp' => $clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                        'message' => '{"consent-name":"test-consent","action":"accepted","identifier":"identifier-123","actor":"actor-456"}',
                    ], $data);

                    return true;
                })
            )
            ->willReturn(1);

        $logger = new DatabaseLog($connection, $clock);

        $logger->log(ConsentStatus::ACCEPTED, 'test-consent', 'identifier-123', 'actor-456');
    }
}
