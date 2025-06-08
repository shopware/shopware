<?php

declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\Migration;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Migration\NoDropStatementInUpdateRule;

/**
 * @internal
 *
 * @extends  RuleTestCase<NoDropStatementInUpdateRule>
 */
#[CoversClass(NoDropStatementInUpdateRule::class)]
class NoDropStatementInUpdateRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([
            __DIR__ . '/../data/NoDropStatementInUpdateRule/Migration1720610754FooBar.php',
            __DIR__ . '/../data/NoDropStatementInUpdateRule/Migration1720610755Foo.php',
            __DIR__ . '/../data/NoDropStatementInUpdateRule/Migration1720610756Bar.php',
        ], [
            // Helper method calls
            ['Usage of method "dropColumnIfExists" is disallowed in the "update" method of a migration to avoid blue green compatibility breaks.', 19],
            ['Usage of method "dropForeignKeyIfExists" is disallowed in the "update" method of a migration to avoid blue green compatibility breaks.', 20],
            ['Usage of method "dropTableIfExists" is disallowed in the "update" method of a migration to avoid blue green compatibility breaks.', 21],

            // SQL statements
            ['Usage of "DROP COLUMN" statements is disallowed in the "update" method of a migration to avoid blue green compatibility breaks.', 25],
            ['Usage of "DROP COLUMN" statements is disallowed in the "update" method of a migration to avoid blue green compatibility breaks.', 26],
            ['Usage of "DROP" statements is disallowed in the "update" method of a migration to avoid blue green compatibility breaks.', 27],
            ['Usage of "DROP FOREIGN KEY" statements is disallowed in the "update" method of a migration to avoid blue green compatibility breaks.', 28],
            ['Usage of "DROP TABLE" statements is disallowed in the "update" method of a migration to avoid blue green compatibility breaks.', 29],

            // within private method
            ['Usage of method "dropColumnIfExists" is disallowed in the "update" method of a migration to avoid blue green compatibility breaks.', 61],
            ['Usage of "DROP COLUMN" statements is disallowed in the "update" method of a migration to avoid blue green compatibility breaks.', 62],
            ['Usage of method "dropColumnIfExists" is disallowed in the "update" method of a migration to avoid blue green compatibility breaks.', 69],

            // Migration with two digit major number
            ['Usage of method "dropColumnIfExists" is disallowed in the "update" method of a migration to avoid blue green compatibility breaks.', 23],
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoDropStatementInUpdateRule();
    }
}
