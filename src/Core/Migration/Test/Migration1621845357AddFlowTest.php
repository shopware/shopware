<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class Migration1621845357AddFlowTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    public function testTablesArePresent(): void
    {
        $flowColumns = array_column($this->getContainer()->get(Connection::class)->fetchAllAssociative('SHOW COLUMNS FROM flow'), 'Field');

        static::assertContains('id', $flowColumns);
        static::assertContains('name', $flowColumns);
        static::assertContains('description', $flowColumns);
        static::assertContains('event_name', $flowColumns);
        static::assertContains('priority', $flowColumns);
        static::assertContains('active', $flowColumns);
        static::assertContains('payload', $flowColumns);
        static::assertContains('invalid', $flowColumns);
        static::assertContains('custom_fields', $flowColumns);
        static::assertContains('created_at', $flowColumns);
        static::assertContains('updated_at', $flowColumns);
    }
}
