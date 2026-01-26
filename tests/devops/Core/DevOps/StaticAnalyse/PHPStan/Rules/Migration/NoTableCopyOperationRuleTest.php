<?php

declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\Migration;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Migration\NoTableCopyOperationRule;

/**
 * @internal
 *
 * @extends  RuleTestCase<NoTableCopyOperationRule>
 */
#[CoversClass(NoTableCopyOperationRule::class)]
class NoTableCopyOperationRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([
            __DIR__ . '/../data/NoTableCopyOperationRule/Migration1769435681ProblematicPattern.php',
            __DIR__ . '/../data/NoTableCopyOperationRule/Migration1769435682ValidPattern.php',
            __DIR__ . '/../data/NoTableCopyOperationRule/Migration1769435680OldMigration.php',
        ], [
            // Should catch ADD COLUMN combined with ADD CONSTRAINT CHECK
            [
                'Combining ADD COLUMN with ADD CONSTRAINT CHECK in the same ALTER TABLE statement requires ALGORITHM=COPY and causes a full table rebuild. Split into separate statements: use MigrationStep::addColumnInstant() for the column, then ADD CONSTRAINT separately.',
                20,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoTableCopyOperationRule();
    }
}
