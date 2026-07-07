<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Consent\Log;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\Log\DatabaseLog;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
class DatabaseLogTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testWriteConsent(): void
    {
        $connection = static::getContainer()->get('Doctrine\DBAL\Connection');

        $connection->executeStatement('DELETE FROM consent_log');

        $logger = new DatabaseLog($connection, new NativeClock());

        $logger->log(
            ConsentStatus::ACCEPTED,
            'test-consent',
            'identifier-123',
            'actor-456'
        );

        $result = $connection->fetchAllAssociative('SELECT * FROM consent_log WHERE consent_name = ?', ['test-consent']);

        static::assertCount(1, $result);

        static::assertSame('test-consent', $result[0]['consent_name']);
        static::assertEquals([
            'consent-name' => 'test-consent',
            'action' => ConsentStatus::ACCEPTED->value,
            'identifier' => 'identifier-123',
            'actor' => 'actor-456',
        ], \json_decode($result[0]['message'], true, flags: \JSON_THROW_ON_ERROR));
    }
}
