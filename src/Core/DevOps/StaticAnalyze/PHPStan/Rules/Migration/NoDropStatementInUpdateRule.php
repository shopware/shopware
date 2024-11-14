<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Migration;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * This rule checks the "update" methods of migrations for usage of "DROP" statements, which are not allowed due to blue-green compatibility reasons.
 * First it checks, if the right context is given, like being in a "MigrationStep" class and in the "update" method.
 * Additionally, this rule only considers migrations since v6.6.0.0.
 * The rule also works under the assumption, that private methods are located after public methods, because of the used code style rules.
 * With this assumption it is possible to also check private methods, that are called within the "update" method.
 * The rule would miss such additional calls, if the methods are located above the "update" method.
 *
 * @internal
 *
 * @implements Rule<ClassMethod>
 */
#[Package('core')]
class NoDropStatementInUpdateRule implements Rule
{
    use InMigrationClassTrait;

    private const MIGRATION_METHOD_NAME_TO_BE_CHECKED = 'update';
    private const DROP_TABLE_REGEX_PATTERN = '/DROP\s+TABLE\s+`?\w+`?/i';
    private const DROP_COLUMN_REGEX_PATTERN = '/ALTER\s+TABLE\s+`?\w+`?\s+DROP\s+COLUMN\s+`?\w+`?/i';
    private const DROP_FOREIGN_KEY_REGEX_PATTERN = '/ALTER\s+TABLE\s+`?\w+`?\s+DROP\s+FOREIGN\s+KEY\s+`?\w+`?/i';
    private const DROP_REGEX_PATTERN = '/ALTER\s+TABLE\s+`?\w+`?\s+DROP\s+(?!INDEX|CONSTRAINT|DEFAULT)`?\w+`?/i';
    private const SW_MAJOR_VERSION_FROM_WHICH_ON_BLUE_GREEN_SHOULD_BE_CHECKED = 6;

    /**
     * @var list<string>
     */
    private static array $publicMethodsOfMigrationsWhichDontNeedToBeChecked = [
        'updateDestructive',
        'getCreationTimestamp',
    ];

    /**
     * @var list<string>
     */
    private static array $disallowedMethodCalls = [
        'dropTableIfExists',
        'dropColumnIfExists',
        'dropForeignKeyIfExists',
    ];

    /**
     * @var array<string, list<string>>
     */
    private array $furtherMethodsToCheck = [];

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof ClassMethod) {
            return [];
        }

        if (!$this->isInMigrationClass($scope)) {
            return [];
        }

        if (!$this->isMigrationAtLeastInMajorVersion6($scope)) {
            return [];
        }

        if (!$node->name instanceof Identifier) {
            return [];
        }

        $methodName = $node->name->name;
        if (\in_array($methodName, self::$publicMethodsOfMigrationsWhichDontNeedToBeChecked, true)) {
            return [];
        }

        // Check if the method is in the same migration class, so methods with the same name from other migrations are not considered
        $migrationClassName = $scope->getClassReflection()?->getName() ?? $scope->getFile();
        if ($methodName === self::MIGRATION_METHOD_NAME_TO_BE_CHECKED
            || \in_array($methodName, $this->furtherMethodsToCheck[$migrationClassName] ?? [], true)
        ) {
            return $this->inspectMethodCalls($node, $migrationClassName);
        }

        return [];
    }

    private function isMigrationAtLeastInMajorVersion6(Scope $scope): bool
    {
        $namespace = $scope->getNamespace() ?? '';
        $swVersionNumberSeparator = strrpos($namespace, '_');
        if ($swVersionNumberSeparator === false) {
            return false;
        }

        $majorNumber = (int) substr($namespace, $swVersionNumberSeparator + 1);

        return $majorNumber >= self::SW_MAJOR_VERSION_FROM_WHICH_ON_BLUE_GREEN_SHOULD_BE_CHECKED;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function inspectMethodCalls(ClassMethod $node, string $migrationClassName): array
    {
        $errors = [];
        foreach ($node->getStmts() ?? [] as $statement) {
            if (!property_exists($statement, 'expr')) {
                continue;
            }

            $statement = $statement->expr;
            if (!$statement instanceof MethodCall) {
                continue;
            }

            $name = $statement->name;
            if (!$name instanceof Identifier) {
                continue;
            }

            $errors = $this->inspectMethodCall($statement, $name, $node, $errors);

            if (!$statement->var instanceof Variable || $statement->var->name !== 'this') {
                continue;
            }

            // Save the method name under the class name, so methods with the same name in other migrations are not considered
            $this->furtherMethodsToCheck[$migrationClassName][] = $name->name;
        }

        return $errors;
    }

    /**
     * @param list<IdentifierRuleError> $errors
     *
     * @return list<IdentifierRuleError>
     */
    private function inspectMethodCall(MethodCall $statement, Identifier $name, ClassMethod $node, array $errors): array
    {
        if (\in_array($name->name, self::$disallowedMethodCalls, true)) {
            $errors[] = RuleErrorBuilder::message(\sprintf(
                'Usage of method "%s" is disallowed in the "update" method of a migration to avoid blue green compatibility breaks.',
                $name->name
            ))
                ->identifier('shopware.dropStatement')
                ->line($statement->getLine())
                ->build();

            return $errors;
        }

        if ($name->name === 'executeStatement') {
            return $this->checkSqlStatements($statement, $node, $errors);
        }

        return $errors;
    }

    /**
     * @param list<IdentifierRuleError> $errors
     *
     * @return list<IdentifierRuleError>
     */
    private function checkSqlStatements(MethodCall $statement, ClassMethod $classMethod, array $errors): array
    {
        $args = $statement->getArgs();
        if ($args === []) {
            return $errors;
        }

        $sqlStatementToCheck = '';
        $firstArgumentValue = $args[0]->value;
        if ($firstArgumentValue instanceof String_) {
            $sqlStatementToCheck = $firstArgumentValue->value;
        } elseif ($firstArgumentValue instanceof Variable) {
            $sqlStatementToCheck = $this->getVariableValue($firstArgumentValue, $classMethod);
        }

        if (preg_match(self::DROP_TABLE_REGEX_PATTERN, $sqlStatementToCheck) === 1) {
            $errors[] = RuleErrorBuilder::message('Usage of "DROP TABLE" statements is disallowed in the "update" method of a migration to avoid blue green compatibility breaks.')
                ->identifier('shopware.dropStatement')
                ->line($statement->getLine())
                ->build();

            return $errors;
        }

        if (preg_match(self::DROP_COLUMN_REGEX_PATTERN, $sqlStatementToCheck) === 1) {
            $errors[] = RuleErrorBuilder::message('Usage of "DROP COLUMN" statements is disallowed in the "update" method of a migration to avoid blue green compatibility breaks.')
                ->identifier('shopware.dropStatement')
                ->line($statement->getLine())
                ->build();

            return $errors;
        }

        if (preg_match(self::DROP_FOREIGN_KEY_REGEX_PATTERN, $sqlStatementToCheck) === 1) {
            $errors[] = RuleErrorBuilder::message('Usage of "DROP FOREIGN KEY" statements is disallowed in the "update" method of a migration to avoid blue green compatibility breaks.')
                ->identifier('shopware.dropStatement')
                ->line($statement->getLine())
                ->build();

            return $errors;
        }

        if (preg_match(self::DROP_REGEX_PATTERN, $sqlStatementToCheck) === 1) {
            $errors[] = RuleErrorBuilder::message('Usage of "DROP" statements is disallowed in the "update" method of a migration to avoid blue green compatibility breaks.')
                ->identifier('shopware.dropStatement')
                ->line($statement->getLine())
                ->build();
        }

        return $errors;
    }

    private function getVariableValue(Variable $variable, ClassMethod $method): string
    {
        foreach ($method->getStmts() ?? [] as $stmt) {
            if ($stmt instanceof Expression && $stmt->expr instanceof Assign) {
                $assign = $stmt->expr;
                if ($assign->var instanceof Variable
                    && $assign->var->name === $variable->name
                    && $assign->expr instanceof String_
                ) {
                    return $assign->expr->value;
                }
            }
        }

        return '';
    }
}
