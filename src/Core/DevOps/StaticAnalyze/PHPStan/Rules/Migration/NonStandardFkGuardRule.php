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
 * Requires raw DDL against {@see self::TABLES_WITH_KNOWN_DRIFT} to go through
 * {@see \Shopware\Core\Framework\Migration\MigrationStep::executeDdlStatement()}, which retries
 * when MySQL 8.4 rejects the statement (bug #118151). The MigrationStep DDL helpers retry
 * themselves. Which shops are affected depends on schema history, so the rule guards the tables
 * with reported drift; the MySQL 8.4 devops test covers the behaviour itself.
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

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier) {
            return [];
        }

        if ($node->name->toString() !== 'executeStatement') {
            return [];
        }

        if (!$this->isInMigrationClass($scope) || !$this->isRecentMigration($scope)) {
            return [];
        }

        $sql = $node->getArgs()[0]->value ?? null;
        if (!$sql instanceof String_) {
            return [];
        }

        $table = $this->getDriftedTableFromSql($sql->value);
        if ($table === null) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'Raw DDL against "%s" must go through MigrationStep::executeDdlStatement(), otherwise it '
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
