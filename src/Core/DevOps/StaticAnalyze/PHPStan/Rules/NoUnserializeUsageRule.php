<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\TestRuleHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Assert\Serialization;

/**
 * This rule detects usage of the unserialize() function and reports it as a security vulnerability.
 * unserialize() can be exploited by attackers to execute arbitrary code if the input is not properly sanitized.
 * Reference: https://www.php.net/manual/en/function.unserialize.php
 *
 * In test classes the error is non-ignorable: use \Shopware\Core\Test\Assert\Serialization instead.
 *
 * @internal
 *
 * @implements Rule<FuncCall>
 */
#[Package('framework')]
class NoUnserializeUsageRule implements Rule
{
    final public const FUNCTION_NAME = 'unserialize';

    final public const RULE_IDENTIFIER = 'shopware.unserializeUsage';

    final public const ERROR_MESSAGE = 'Usage of unserialize() function in class "%s" is disallowed because it may introduce security vulnerabilities.';

    final public const ERROR_TIP = 'If you need to unserialize data, consider using a safe alternative such as json format or a dedicated serializer.';

    final public const ERROR_TIP_TESTS = 'In test classes, use \Shopware\Core\Test\Assert\Serialization::assertRoundTrip(), ::assertUnserializedInstanceOf(), ::assertUnserializedIsArray(), ::assertUnserializedEquals(), or ::assertUnserializedSame() instead.';

    /**
     * Classes that are explicitly allowed to call unserialize() because they
     * centralize its usage behind a safe, typed assertion API.
     *
     * @var list<class-string>
     */
    private const ALLOWLIST = [
        Serialization::class,
    ];

    public function __construct(private readonly ReflectionProvider $reflectionProvider)
    {
    }

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

        if (!$this->reflectionProvider->hasFunction($node->name, $scope)) {
            return [];
        }

        $function = $this->reflectionProvider->getFunction($node->name, $scope);

        if (!$function->isBuiltin() || $function->getName() !== self::FUNCTION_NAME) {
            return [];
        }

        $className = $scope->getClassReflection()?->getName() ?? '';

        if (\in_array($className, self::ALLOWLIST, true)) {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        $isTestClass = $classReflection !== null && TestRuleHelper::isTestClass($classReflection);

        $builder = RuleErrorBuilder::message(\sprintf(self::ERROR_MESSAGE, $className))
            ->identifier(self::RULE_IDENTIFIER)
            ->tip($isTestClass ? self::ERROR_TIP_TESTS : self::ERROR_TIP);

        if ($isTestClass) {
            $builder = $builder->nonIgnorable();
        }

        return [$builder->build()];
    }
}
