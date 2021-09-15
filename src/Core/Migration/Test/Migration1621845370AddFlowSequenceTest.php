<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class Migration1621845370AddFlowSequenceTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    public function testTablesArePresent(): void
    {
        $flowSequenceColumns = array_column($this->getContainer()->get(Connection::class)->fetchAllAssociative('SHOW COLUMNS FROM flow_sequence'), 'Field');

        static::assertContains('id', $flowSequenceColumns);
        static::assertContains('flow_id', $flowSequenceColumns);
        static::assertContains('parent_id', $flowSequenceColumns);
        static::assertContains('rule_id', $flowSequenceColumns);
        static::assertContains('config', $flowSequenceColumns);
        static::assertContains('position', $flowSequenceColumns);
        static::assertContains('display_group', $flowSequenceColumns);
        static::assertContains('true_case', $flowSequenceColumns);
        static::assertContains('custom_fields', $flowSequenceColumns);
        static::assertContains('created_at', $flowSequenceColumns);
        static::assertContains('updated_at', $flowSequenceColumns);
    }
}
