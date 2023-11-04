<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_3\Migration1605861407RuleAssociationsToRestrict;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1605861407RuleAssociationsToRestrict
 */
class Migration1605861407RuleAssociationsToRestrictTest extends TestCase
{
    public function testUpdateRuleAssociationsToRestrict(): void
    {
        $conn = KernelLifecycleManager::getConnection();

        $database = $conn->fetchOne('select database();');

        $migration = new Migration1605861407RuleAssociationsToRestrict();
        $migration->update($conn);

        /** @var array<string, mixed> $foreignKeyInfoUpdated */
        $foreignKeyInfoUpdated = $conn->fetchAssociative('SELECT * FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE TABLE_NAME = "product_price" AND REFERENCED_TABLE_NAME = "rule" AND CONSTRAINT_SCHEMA = "' . $database . '";');

        static::assertNotEmpty($foreignKeyInfoUpdated);
        static::assertEquals($foreignKeyInfoUpdated['CONSTRAINT_NAME'], 'fk.product_price.rule_id');
        static::assertEquals($foreignKeyInfoUpdated['DELETE_RULE'], 'RESTRICT');

        /** @var array<string, mixed>[] $foreignKeyInfoUpdated */
        $foreignKeyInfoUpdated = $conn->fetchAllAssociative('SELECT * FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE TABLE_NAME = "shipping_method_price" AND REFERENCED_TABLE_NAME = "rule" AND CONSTRAINT_SCHEMA = "' . $database . '" ORDER BY CONSTRAINT_NAME;');

        static::assertNotEmpty($foreignKeyInfoUpdated);
        static::assertCount(2, $foreignKeyInfoUpdated);
        static::assertEquals($foreignKeyInfoUpdated[1]['CONSTRAINT_NAME'], 'fk.shipping_method_price.rule_id');
        static::assertEquals($foreignKeyInfoUpdated[1]['DELETE_RULE'], 'RESTRICT');

        static::assertEquals($foreignKeyInfoUpdated[0]['CONSTRAINT_NAME'], 'fk.shipping_method_price.calculation_rule_id');
        static::assertEquals($foreignKeyInfoUpdated[0]['DELETE_RULE'], 'RESTRICT');

        /** @var array<string, mixed> $foreignKeyInfoUpdated */
        $foreignKeyInfoUpdated = $conn->fetchAssociative('SELECT * FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE TABLE_NAME = "payment_method" AND REFERENCED_TABLE_NAME = "rule" AND CONSTRAINT_SCHEMA = "' . $database . '";');

        static::assertNotEmpty($foreignKeyInfoUpdated);
        static::assertEquals($foreignKeyInfoUpdated['CONSTRAINT_NAME'], 'fk.payment_method.availability_rule_id');
        static::assertEquals($foreignKeyInfoUpdated['DELETE_RULE'], 'RESTRICT');
    }
}
