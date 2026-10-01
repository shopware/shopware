<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1774280952AddUserModifiedToMailTemplate;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1774280952AddUserModifiedToMailTemplate::class)]
class Migration1774280952AddUserModifiedToMailTemplateTest extends TestCase
{
    private readonly Connection $connection;

    private readonly Migration1774280952AddUserModifiedToMailTemplate $migration;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1774280952AddUserModifiedToMailTemplate();

        try {
            $this->connection->executeStatement('ALTER TABLE `mail_template` DROP COLUMN `was_modified_by_user`');
        } catch (\Throwable) {
            // Column already does not exist, ignore
        }
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1774280952, $this->migration->getCreationTimestamp());
    }

    public function testAddColumn(): void
    {
        $this->migration->update($this->connection);
        // run twice to verify idempotency
        $this->migration->update($this->connection);

        $columns = array_column(
            $this->connection->fetchAllAssociative('SHOW COLUMNS FROM `mail_template` LIKE \'was_modified_by_user\''),
            'Field'
        );

        static::assertContains('was_modified_by_user', $columns);

        $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `mail_template` WHERE `was_modified_by_user` = 0');

        static::assertSame(0, $count, 'On update, all existing mail templates should have was_modified_by_user = 1');
    }
}
