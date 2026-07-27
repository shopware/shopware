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
 * Requires DDL against {@see self::TABLES_WITH_KNOWN_DRIFT} to run inside
 * {@see \Shopware\Core\Framework\Migration\MigrationStep::withRelaxedNonStandardFkGuard()},
 * otherwise it fails on MySQL 8.4 (bug #118151).
 *
 * Whether a shop is affected depends on its schema history, which static analysis cannot see, so
 * the rule guards the tables where drift has been reported rather than a derived category.
 *
 * The wrapping check is coarse: it only asserts the statement sits inside some closure. The
 * MySQL 8.4 regression test in the devops suite covers the behaviour itself.
 *
 * @internal
 *
 * @implements Rule<MethodCall>
 */
#[Package('framework')]
class NonStandardFkGuardRule implements Rule
{
    use InMigrationClassTrait;

    /**
     * Older migrations predate the rule; the ones known to break have the guard applied already.
     */
    private const CUTOFF_UNIX_TIMESTAMP = '2026-07-24 00:00:00';

    /**
     * Extend when drift against another table is reported. `product` covers #16240 and #13039.
     */
    private const TABLES_WITH_KNOWN_DRIFT = [
        'product',
    ];

    /**
     * MigrationStep helpers that issue ALTER TABLE, with the position of their table argument.
     */
    private const TABLE_ARGUMENT_METHODS = [
        'addColumn' => 1,
        'dropColumnIfExists' => 1,
        'dropForeignKeyIfExists' => 1,
        'dropIndexIfExists' => 1,
        'updateInheritance' => 1,
    ];

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier) {
            return [];
        }

        if (!$this->isInMigrationClass($scope) || !$this->isRecentMigration($scope)) {
            return [];
        }

        $table = $this->getTargetedDriftedTable($node);
        if ($table === null) {
            return [];
        }

        // Anything inside a closure is assumed to be wrapped by the guard helper.
        if ($scope->isInAnonymousFunction()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'DDL against "%s" must run inside MigrationStep::withRelaxedNonStandardFkGuard(), otherwise it '
                . 'fails on MySQL 8.4 for the shops that carry non-standard foreign key drift against that '
                . 'table (MySQL bug #118151).',
                $table
            ))
                ->identifier('shopware.nonStandardFkGuard')
                ->build(),
        ];
    }

    private function isRecentMigration(Scope $scope): bool
    {
        $className = $scope->getClassReflection()?->getName() ?? '';
        $className = substr($className, (int) strrpos($className, '\\') + 1);

        if (preg_match('/Migration(\d{10})/', $className, $matches) !== 1) {
            return false;
        }

        return (int) $matches[1] > (int) strtotime(self::CUTOFF_UNIX_TIMESTAMP);
    }

    private function getTargetedDriftedTable(MethodCall $node): ?string
    {
        $method = $node->name;
        if (!$method instanceof Identifier) {
            return null;
        }

        $args = $node->getArgs();

        $tableArgumentPosition = self::TABLE_ARGUMENT_METHODS[$method->toString()] ?? null;
        if ($tableArgumentPosition !== null) {
            $table = $args[$tableArgumentPosition]->value ?? null;

            return $table instanceof String_ && $this->hasKnownDrift($table->value) ? $table->value : null;
        }

        if ($args === []) {
            return null;
        }

        $sql = $args[0]->value;
        if (!$sql instanceof String_) {
            return null;
        }

        return $this->getDriftedTableFromSql($sql->value);
    }

    private function getDriftedTableFromSql(string $sql): ?string
    {
        $patterns = [
            '/ALTER\s+TABLE\s+`?(\w+)`?/i',
            '/CREATE\s+(?:UNIQUE\s+|FULLTEXT\s+)?INDEX\s+\S+\s+ON\s+`?(\w+)`?/i',
            '/DROP\s+INDEX\s+\S+\s+ON\s+`?(\w+)`?/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $sql, $matches) === 1 && $this->hasKnownDrift($matches[1])) {
                return strtolower($matches[1]);
            }
        }

        return null;
    }

    private function hasKnownDrift(string $table): bool
    {
        return \in_array(strtolower($table), self::TABLES_WITH_KNOWN_DRIFT, true);
    }
}
