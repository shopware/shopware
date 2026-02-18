<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * This rule detects usage of the unserialize() function and reports it as a security vulnerability.
 * unserialize() can be exploited by attackers to execute arbitrary code if the input is not properly sanitized.
 * Reference: https://www.php.net/manual/de/function.unserialize.php
 *
 * @internal
 *
 * @implements Rule<FuncCall>
 */
#[Package('framework')]
class NoUnserializeUsageRule implements Rule
{
    use InTestClassTrait;

    final public const FUNCTION_NAME = 'unserialize';

    final public const RULE_IDENTIFIER = 'shopware.unserializeUsage';

    final public const ERROR_MESSAGE = 'Usage of unserialize() function in class "%s" is disallowed because it may introduce security vulnerabilities.';

    final public const ERROR_TIP = 'If you need to unserialize data, consider using a safe alternative such as json format or a dedicated serializer.';

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall) {
            return [];
        }

        if (!$node->name instanceof Name) {
            return [];
        }

        if (\ltrim($node->name->toLowerString(), '\\') !== self::FUNCTION_NAME) {
            return [];
        }

        $className = $scope->getClassReflection()?->getName() ?? '';

        return [
            RuleErrorBuilder::message(\sprintf(self::ERROR_MESSAGE, $className))
                ->identifier(self::RULE_IDENTIFIER)
                ->tip(self::ERROR_TIP)
                ->build(),
        ];
    }
}
