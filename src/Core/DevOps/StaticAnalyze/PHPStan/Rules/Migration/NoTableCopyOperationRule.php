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

    /**
     * Unix timestamp cutoff - migrations created after this timestamp are checked.
     * Using integer to avoid timezone-dependent strtotime() parsing.
     */
    private const CUTOFF_UNIX_TIMESTAMP = 1737899680; // 2026-01-26 13:54:40 UTC

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

        // ADD COLUMN combined with ADD CONSTRAINT CHECK in same statement
        // This requires COPY algorithm because CHECK constraints can't use INSTANT/INPLACE
        if (preg_match('/ALTER\s+TABLE\s+.+?\s+ADD\s+COLUMN\s+.+?\s*,\s*ADD\s+CONSTRAINT\s+.+?\s+CHECK/i', $sql)) {
            return [
                RuleErrorBuilder::message('Combining ADD COLUMN with ADD CONSTRAINT CHECK in the same ALTER TABLE statement requires ALGORITHM=COPY and causes a full table rebuild. Split into separate statements: use MigrationStep::addColumnInstant() for the column, then ADD CONSTRAINT separately.')
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

            return $migrationUnixTimestamp > self::CUTOFF_UNIX_TIMESTAMP;
        }

        return false;
    }
}
