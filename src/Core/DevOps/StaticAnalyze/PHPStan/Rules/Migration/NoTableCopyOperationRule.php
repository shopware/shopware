<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Migration;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @implements Rule<MethodCall>
 */
#[Package('framework')]
class NoTableCopyOperationRule implements Rule
{
    use InMigrationClassTrait;

    private const CUTOFF_UNIX_TIMESTAMP = '2026-01-26 13:54:40';

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall) {
            return [];
        }

        if (!$this->isInMigrationClass($scope)) {
            return [];
        }

        if (!$this->isRecentMigration($scope)) {
            return [];
        }

        if (!$node->name instanceof Identifier) {
            return [];
        }

        if ($node->name->name !== 'executeStatement') {
            return [];
        }

        if (empty($node->getArgs())) {
            return [];
        }

        $arg = $node->getArgs()[0]->value;
        if (!$arg instanceof String_) {
            return [];
        }

        $sql = $arg->value;

        // Pattern 1: ADD COLUMN combined with ADD CONSTRAINT CHECK in same statement
        // This requires COPY algorithm because CHECK constraints can't use INSTANT/INPLACE
        if (preg_match('/ALTER\s+TABLE\s+.+?\s+ADD\s+COLUMN\s+.+?\s*,\s*ADD\s+CONSTRAINT\s+.+?\s+CHECK/i', $sql)) {
            return [
                RuleErrorBuilder::message('Combining ADD COLUMN with ADD CONSTRAINT CHECK in the same ALTER TABLE statement requires ALGORITHM=COPY and causes a full table rebuild. Split into separate statements: use MigrationStep::addColumnInstant() for the column, then ADD CONSTRAINT separately.')
                    ->identifier('shopware.tableCopyOperation')
                    ->build(),
            ];
        }

        // Pattern 2: MODIFY COLUMN with data type change (not just length increase)
        // This is a heuristic - actual detection would require parsing the full SQL
        // For now, we'll catch obvious type changes like INT to VARCHAR, etc.
        if (preg_match('/ALTER\s+TABLE\s+.+?\s+MODIFY\s+COLUMN\s+.+?\s+(INT|BIGINT|SMALLINT|TINYINT|MEDIUMINT)\s+/i', $sql)
            && preg_match('/MODIFY\s+COLUMN\s+.+?\s+(VARCHAR|CHAR|TEXT|BLOB|JSON|DATE|DATETIME|TIMESTAMP)/i', $sql)
        ) {
            return [
                RuleErrorBuilder::message('MODIFY COLUMN with data type changes requires ALGORITHM=COPY and causes a full table rebuild. Consider if this is necessary or use a different approach.')
                    ->identifier('shopware.tableCopyOperation')
                    ->build(),
            ];
        }

        return [];
    }

    private function isRecentMigration(Scope $scope): bool
    {
        $className = $scope->getClassReflection()?->getName() ?? '';
        $className = substr($className, (int) strrpos($className, '\\') + 1);

        if (preg_match('/Migration(\d{10})/', $className, $matches)) {
            $migrationUnixTimestamp = (int) $matches[1];
            $cutoffUnixTimestamp = strtotime(self::CUTOFF_UNIX_TIMESTAMP);

            return $migrationUnixTimestamp > $cutoffUnixTimestamp;
        }

        return false;
    }
}
