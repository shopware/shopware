<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\AddColumnRule;

/**
 * @internal
 *
 * @extends RuleTestCase<AddColumnRule>
 */
#[CoversClass(AddColumnRule::class)]
class AddColumnRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse(
            [__DIR__ . '/data/AddColumnRule/UseNewFunction.php'],
            []
        );

        $this->analyse(
            [__DIR__ . '/data/AddColumnRule/OtherAddStatements.php'],
            []
        );

        $this->analyse([__DIR__ . '/data/AddColumnRule/UsePlainSql.php'], [
            [
                'Do not use `ALTER TABLE ... ADD COLUMN` in migration. Use MigrationStep::addColumn instead',
                20,
            ],
        ]);

        $this->analyse([__DIR__ . '/data/AddColumnRule/UsePlainSqlJustAdd.php'], [
            [
                'Do not use `ALTER TABLE ... ADD COLUMN` in migration. Use MigrationStep::addColumn instead',
                20,
            ],
        ]);
    }

    /**
     * @return AddColumnRule
     */
    protected function getRule(): Rule
    {
        return new AddColumnRule();
    }
}
