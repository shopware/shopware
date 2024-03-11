<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use Doctrine\DBAL\Connection;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @implements Rule<MethodCall>
 *
 * @internal
 */
#[Package('core')]
class AddColumnRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node
     *
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall) {
            return [];
        }

        if (!$scope->isInClass()) {
            return [];
        }

        // ignore namespace V6_4 and V6_3
        if (str_contains($scope->getClassReflection()->getName(), 'Shopware\\Core\\Migration\\V6_4') || str_contains($scope->getClassReflection()->getName(), 'Shopware\\Core\\Migration\\V6_3')) {
            return [];
        }

        if (!$scope->getClassReflection()->isSubclassOf(MigrationStep::class)) {
            return [];
        }

        // is \Doctrine\DBAL\Connection::executeStatement?
        if (!$node->name instanceof Node\Identifier) {
            return [];
        }

        if ($node->name->name !== 'executeStatement') {
            return [];
        }

        if (!$node->var instanceof Node\Expr\Variable) {
            return [];
        }

        $varType = $scope->getType($node->var);

        if (!$varType->isSuperTypeOf(new ObjectType(Connection::class))->yes()) {
            return [];
        }

        // is called with `ADD COLUMN` in string
        if (\count($node->args) !== 1) {
            return [];
        }

        if (!\array_key_exists(0, $node->args)) {
            return [];
        }

        $arg = $node->args[0];

        if (!$arg instanceof Node\Arg) {
            return [];
        }

        $arg = $arg->value;

        if (!$arg instanceof Node\Scalar\String_) {
            return [];
        }

        if (str_contains($arg->value, 'GENERATED ALWAYS AS')) {
            return [];
        }

        $pattern = '/ALTER TABLE .* ADD CONSTRAINT.*/m';
        if (preg_match($pattern, $arg->value)) {
            return [];
        }

        $pattern = '/ALTER TABLE .* ADD INDEX.*/m';
        if (preg_match($pattern, $arg->value)) {
            return [];
        }

        $pattern = '/ALTER TABLE .* ADD UNIQUE INDEX.*/m';
        if (preg_match($pattern, $arg->value)) {
            return [];
        }
        $pattern = '/ALTER TABLE .* ADD FOREIGN KEY.*/m';
        if (preg_match($pattern, $arg->value)) {
            return [];
        }

        $pattern = '/ALTER TABLE .* ADD .*/m';
        if (preg_match($pattern, $arg->value)) {
            return [
                RuleErrorBuilder::message('Do not use `ALTER TABLE ... ADD COLUMN` in migration. Use MigrationStep::addColumn instead')->build(),
            ];
        }

        return [];
    }
}
