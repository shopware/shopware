<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1772443355InitiallySetRevokedConsentsToDeclined;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(Migration1772443355InitiallySetRevokedConsentsToDeclined::class)]
class Migration1772443355InitiallySetRevokedConsentsToDeclinedTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DELETE FROM `consent_state`');
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1772443355, (new Migration1772443355InitiallySetRevokedConsentsToDeclined())->getCreationTimestamp());
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1772443355InitiallySetRevokedConsentsToDeclined();

        static::assertSame(1772443355, $migration->getCreationTimestamp());
    }

    public function testUpdatesRevokedStatus(): void
    {
        $this->connection->executeStatement('DELETE FROM `consent_state`');
        $this->connection->executeStatement('INSERT INTO `consent_state`
            (`id`, `name`, `identifier`, `state`, `actor`, `updated_at`) VALUES
            (UNHEX(REPLACE(UUID(), "-", "")), "revoked_consent", "system", "revoked", "test_user", NOW()),
            (UNHEX(REPLACE(UUID(), "-", "")), "accepted_consent", "system", "accepted", "test_user", NOW())
        ');

        $migration = new Migration1772443355InitiallySetRevokedConsentsToDeclined();
        $migration->update($this->connection);

        $result = $this->connection->fetchAllAssociative('SELECT `name`, `state` FROM `consent_state`');
        static::assertEquals([
            [
                'name' => 'revoked_consent',
                'state' => 'declined',
            ], [
                'name' => 'accepted_consent',
                'state' => 'accepted',
            ],
        ], $result);
    }
}
