<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1656928097AddNewsletterRecipientEmailIndex;

/**
 * @internal
 */
class Migration1656928097AddNewsletterRecipientEmailIndexTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $container = $this->getContainer();
        $this->connection = $container->get(Connection::class);

        try {
            $this->connection->executeStatement('DROP INDEX `idx.newsletter_recipient.email` ON `newsletter_recipient`');
        } catch (\Throwable $e) {
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
