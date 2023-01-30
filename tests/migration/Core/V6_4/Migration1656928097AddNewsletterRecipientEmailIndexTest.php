<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1656928097AddNewsletterRecipientEmailIndex;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1656928097AddNewsletterRecipientEmailIndex
 */
class Migration1656928097AddNewsletterRecipientEmailIndexTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();

        try {
            $this->connection->executeStatement('DROP INDEX `idx.newsletter_recipient.email` ON `newsletter_recipient`');
        } catch (\Throwable) {
        }
    }

    public function testIndexExists(): void
    {
        $m = new Migration1656928097AddNewsletterRecipientEmailIndex();
        $m->update($this->connection);

        $this->assertIndexExists();
    }

    public function testMultipleExecutions(): void
    {
        $migration = new Migration1656928097AddNewsletterRecipientEmailIndex();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $this->assertIndexExists();
    }

    public function assertIndexExists(): void
    {
        $indices = $this->connection->getSchemaManager()->listTableIndexes('newsletter_recipient');
        static::assertArrayHasKey('idx.newsletter_recipient.email', $indices);
    }
}
