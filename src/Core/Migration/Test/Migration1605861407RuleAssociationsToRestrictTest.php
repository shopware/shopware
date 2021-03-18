<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\Migration1605861407RuleAssociationsToRestrict;

class Migration1605861407RuleAssociationsToRestrictTest extends TestCase
{
    use KernelTestBehaviour;

    public function testUpdateRuleAssociationsToRestrict(): void
    {
        $conn = $this->getContainer()->get(Connection::class);

        $database = $conn->fetchColumn('select database();');

        $migration = new Migration1605861407RuleAssociationsToRestrict();
        $migration->update($conn);

        $foreignKeyInfoUpdated = $conn->fetchAssoc('SELECT * FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE TABLE_NAME = "product_price" AND REFERENCED_TABLE_NAME = "rule" AND CONSTRAINT_SCHEMA = "' . $database . '";') ?? [];

        static::assertNotEmpty($foreignKeyInfoUpdated);
        static::assertEquals($foreignKeyInfoUpdated['CONSTRAINT_NAME'], 'fk.product_price.rule_id');
        static::assertEquals($foreignKeyInfoUpdated['DELETE_RULE'], 'RESTRICT');

        $foreignKeyInfoUpdated = $conn->fetchAll('SELECT * FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE TABLE_NAME = "shipping_method_price" AND REFERENCED_TABLE_NAME = "rule" AND CONSTRAINT_SCHEMA = "' . $database . '" ORDER BY CONSTRAINT_NAME;') ?? [];

        static::assertNotEmpty($foreignKeyInfoUpdated);
        static::assertCount(2, $foreignKeyInfoUpdated);
        static::assertEquals($foreignKeyInfoUpdated[1]['CONSTRAINT_NAME'], 'fk.shipping_method_price.rule_id');
        static::assertEquals($foreignKeyInfoUpdated[1]['DELETE_RULE'], 'RESTRICT');

        static::assertEquals($foreignKeyInfoUpdated[0]['CONSTRAINT_NAME'], 'fk.shipping_method_price.calculation_rule_id');
        static::assertEquals($foreignKeyInfoUpdated[0]['DELETE_RULE'], 'RESTRICT');

        $foreignKeyInfoUpdated = $conn->fetchAssoc('SELECT * FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE TABLE_NAME = "payment_method" AND REFERENCED_TABLE_NAME = "rule" AND CONSTRAINT_SCHEMA = "' . $database . '";') ?? [];

        static::assertNotEmpty($foreignKeyInfoUpdated);
        static::assertEquals($foreignKeyInfoUpdated['CONSTRAINT_NAME'], 'fk.payment_method.availability_rule_id');
        static::assertEquals($foreignKeyInfoUpdated['DELETE_RULE'], 'RESTRICT');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
