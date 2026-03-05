<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\Log;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\Log\DatabaseLog;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(DatabaseLog::class)]
class DatabaseLogTest extends TestCase
{
    public function testWritesToDB(): void
    {
        $connection = $this->createMock(Connection::class);

        $connection->method('insert')
            ->with(
                'consent_log',
                static::callback(function (array $data) {
                    static::assertArrayIsEqualToArrayOnlyConsideringListOfKeys([
                        'consent_name' => 'test-consent',
                        'timestamp' => $this->anything(),
                        'message' => '{"consent-name":"test-consent","action":"accepted","identifier":"identifier-123","actor":"actor-456"}',
                    ], $data, ['consent_name', 'message']);

                    return true;
                })
            )
            ->willReturn(1);

        $logger = new DatabaseLog($connection);

        $logger->log(ConsentStatus::ACCEPTED, 'test-consent', 'identifier-123', 'actor-456');
    }
}
