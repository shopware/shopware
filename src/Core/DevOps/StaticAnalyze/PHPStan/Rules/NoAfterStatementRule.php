<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @implements Rule<MethodCall>
 */
#[Package('core')]
class NoAfterStatementRule implements Rule
{
    private const CUTOFF_UNIX_TIMESTAMP = '2023-10-11 00:00:00';

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall) {
            return [];
        }

        if (!$this->isRecentMigration($scope)) {
            return [];
        }

        if (!$node->name instanceof Identifier) {
            return [];
        }

        if (empty($node->getArgs())) {
            return [];
        }

        $arg = $node->getArgs()[0]->value;
        if (!$arg instanceof String_) {
            return [];
        }

        $pattern = '/ALTER\s+TABLE\s+.+?\s+ADD\s+.+?\s+AFTER\s+`?[a-zA-Z0-9_]+`?/i';

        if (preg_match($pattern, $arg->value)) {
            return [RuleErrorBuilder::message('Usage of ALTER TABLE .. AFTER is disallowed in migrations to avoid implicit temporary table usage.')->identifier('shopware.afterStatement')->build()];
        }

        return [];
    }

    private function isRecentMigration(Scope $scope): bool
    {
        $class = $scope->getClassReflection();
        if (!$class || !$class->isSubclassOf(MigrationStep::class)) {
            return false;
        }

        $className = substr($class->getName(), strrpos($class->getName(), '\\') + 1);

        if (preg_match('/Migration(\d{10})/', $className, $matches)) {
            $migrationUnixTimestamp = (int) $matches[1];
            $cutoffUnixTimestamp = strtotime(self::CUTOFF_UNIX_TIMESTAMP);

            return $migrationUnixTimestamp > $cutoffUnixTimestamp;
        }

        return false;
    }
}
