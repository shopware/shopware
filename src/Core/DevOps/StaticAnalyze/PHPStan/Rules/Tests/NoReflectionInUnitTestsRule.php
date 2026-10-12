<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\Framework\Log\Package;

/**
 * A unit test exercises its subject through the public API. Reflection lets it read or write private state,
 * call private methods or enumerate members instead, which couples the test to the implementation and keeps
 * passing when the behaviour breaks. This rule reports, in the enabled unit namespaces ({@see Configuration}):
 *
 *  - instantiating a PHP reflection class (`new \ReflectionClass(...)`, `\ReflectionProperty`, `\ReflectionMethod`, ...),
 *  - `Closure::bind()`, `Closure::bindTo()` and `Closure::call()`, which rebind a closure into the subject's scope.
 *
 * @implements Rule<Expr>
 *
 * @internal
 */
#[Package('framework')]
class NoReflectionInUnitTestsRule implements Rule
{
    public const ERROR_REFLECTION = 'Unit tests must not use %s. Test the subject through its public API: build it with its constructor and test doubles, then assert on what it returns or records.';

    public const ERROR_CLOSURE_BINDING = 'Unit tests must not use Closure::%s() to reach into another object\'s scope. Test the subject through its public API: build it with its constructor and test doubles, then assert on what it returns or records.';

    private const IDENTIFIER = 'shopware.reflectionInUnitTest';

    private const CLOSURE_BINDING_METHODS = ['bind', 'bindto', 'call'];

    /**
     * @var list<string>
     */
    private readonly array $enabledNamespaces;

    public function __construct(Configuration $configuration)
    {
        $this->enabledNamespaces = $configuration->getReflectionInUnitTestsEnabledNamespaces();
    }

    public function getNodeType(): string
    {
        return Expr::class;
    }

    /**
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof New_ && !$node instanceof StaticCall && !$node instanceof MethodCall) {
            return [];
        }

        if (!$this->isEnabledNamespace($scope->getNamespace())) {
            return [];
        }

        if ($node instanceof New_) {
            $className = $node->class instanceof Name ? $scope->resolveName($node->class) : null;
            if ($className === null || !$this->isReflectionClass($className)) {
                return [];
            }

            return [$this->error(\sprintf(self::ERROR_REFLECTION, '\\' . $className))];
        }

        if (!$node->name instanceof Identifier || !\in_array($node->name->toLowerString(), self::CLOSURE_BINDING_METHODS, true)) {
            return [];
        }

        if ($node instanceof StaticCall) {
            if (!$node->class instanceof Name || $scope->resolveName($node->class) !== \Closure::class) {
                return [];
            }

            return [$this->error(\sprintf(self::ERROR_CLOSURE_BINDING, $node->name->toString()))];
        }

        if (!(new ObjectType(\Closure::class))->isSuperTypeOf($scope->getType($node->var))->yes()) {
            return [];
        }

        return [$this->error(\sprintf(self::ERROR_CLOSURE_BINDING, $node->name->toString()))];
    }

    /**
     * PHP's reflection classes all live in the root namespace under the `Reflection` prefix.
     */
    private function isReflectionClass(string $className): bool
    {
        return !str_contains($className, '\\')
            && str_starts_with($className, 'Reflection')
            && class_exists($className, false);
    }

    private function isEnabledNamespace(?string $namespace): bool
    {
        if ($namespace === null) {
            return false;
        }

        foreach ($this->enabledNamespaces as $enabledNamespace) {
            if (str_starts_with($namespace . '\\', $enabledNamespace)) {
                return true;
            }
        }

        return false;
    }

    private function error(string $message): RuleError
    {
        return RuleErrorBuilder::message($message)->identifier(self::IDENTIFIER)->build();
    }
}
