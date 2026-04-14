<?php

declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\Migration;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Migration\AddColumnRule;

/**
 * @internal
 *
 * @extends  RuleTestCase<AddColumnRule>
 */
class AddColumnRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([
            __DIR__ . '/../data/AddColumnRule/Migration1769435681ProblematicPattern.php',
            __DIR__ . '/../data/AddColumnRule/Migration1769435682ValidPattern.php',
        ], [
            [
                'Combining ADD COLUMN with ADD CONSTRAINT CHECK in the same ALTER TABLE statement requires ALGORITHM=COPY and causes a full table rebuild. Split into separate statements: use MigrationStep::addColumn() for the column, then ADD CONSTRAINT separately.',
                20,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new AddColumnRule();
    }
}
