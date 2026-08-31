<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Shopware\Core\Framework\Log\Package;

/**
 * Tests must not reach into non-public methods of Shopware classes via reflection: test the
 * behaviour through the public API instead. Third-party and test-support targets, public methods,
 * properties, and metadata reads stay allowed.
 *
 * Reports `new \ReflectionMethod(...)` and `ReflectionClass::getMethod()` when the target method
 * resolves as non-public, and `ReflectionMethod::setAccessible()` for any target, a no-op since
 * PHP 8.1.
 *
 * @implements Rule<CallLike>
 *
 * @internal
 */
#[Package('framework')]
class NoReflectionOnNonPublicMethodsRule implements Rule
{
    public const ERROR_NON_PUBLIC = 'Tests must not use reflection to access the non-public method %s::%s(). Test the behaviour through the public API, or restructure the code (e.g. extract the logic into a collaborator) so it is publicly testable.';

    public const ERROR_SET_ACCESSIBLE = 'ReflectionMethod::setAccessible() has no effect since PHP 8.1 and only signals reflective access to a non-public method. Remove it and test through the public API instead.';

    private const IDENTIFIER = 'shopware.reflectionOnNonPublicMethod';

    /**
     * Reflecting into a third-party class can be unavoidable when a vendor API offers no public
     * alternative, so only our own code is reported.
     */
    private const OWN_NAMESPACE_PREFIX = 'Shopware\\';

    /**
     * Test-support classes are not production API.
     */
    private const TEST_SUPPORT_NAMESPACE_PREFIX = 'Shopware\\Tests\\';

    public function __construct(private readonly ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    /**
     * @param CallLike $node
     *
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $testClass = $scope->getClassReflection();
        if (!$testClass || !TestRuleHelper::isTestClass($testClass)) {
            return [];
        }

        if ($node instanceof New_) {
            return $this->checkNewReflectionMethod($node, $scope);
        }

        if ($node instanceof MethodCall && $node->name instanceof Identifier) {
            if ($node->name->name === 'getMethod') {
                return $this->checkReflectionClassGetMethod($node, $scope);
            }

            if ($node->name->name === 'setAccessible') {
                return $this->checkSetAccessible($node, $scope);
            }
        }

        return [];
    }

    /**
     * @return list<RuleError>
     */
    private function checkNewReflectionMethod(New_ $node, Scope $scope): array
    {
        if (!$node->class instanceof Name || $node->class->toString() !== \ReflectionMethod::class) {
            return [];
        }

        $args = $node->getArgs();

        if (\count($args) >= 2) {
            $classNames = $scope->getType($args[0]->value)->getObjectTypeOrClassStringObjectType()->getObjectClassNames();
            $methodNames = $this->resolveConstantStrings($scope->getType($args[1]->value));

            return $this->buildErrorsForNonPublicTargets($classNames, $methodNames);
        }

        // single-argument form: new \ReflectionMethod('Fully\Qualified\Target::privateMethod')
        if (\count($args) === 1) {
            $classNames = [];
            $methodNames = [];
            foreach ($this->resolveConstantStrings($scope->getType($args[0]->value)) as $combined) {
                if (str_contains($combined, '::')) {
                    [$class, $method] = explode('::', $combined, 2);
                    $classNames[] = $class;
                    $methodNames[] = $method;
                }
            }

            return $this->buildErrorsForNonPublicTargets($classNames, $methodNames);
        }

        return [];
    }

    /**
     * @return list<RuleError>
     */
    private function checkReflectionClassGetMethod(MethodCall $node, Scope $scope): array
    {
        $receiverType = $scope->getType($node->var);
        if ($receiverType instanceof NeverType) {
            return [];
        }

        if (!(new ObjectType(\ReflectionClass::class))->isSuperTypeOf($receiverType)->yes()) {
            return [];
        }

        // `new \ReflectionClass(Target::class)` types as ReflectionClass<Target>; without the
        // template argument the target class stays `object` and the call is skipped (calibrated).
        $classNames = $receiverType->getTemplateType(\ReflectionClass::class, 'T')->getObjectClassNames();

        $args = $node->getArgs();
        if ($args === []) {
            return [];
        }
        $methodNames = $this->resolveConstantStrings($scope->getType($args[0]->value));

        return $this->buildErrorsForNonPublicTargets($classNames, $methodNames);
    }

    /**
     * @return list<RuleError>
     */
    private function checkSetAccessible(MethodCall $node, Scope $scope): array
    {
        $receiverType = $scope->getType($node->var);
        if ($receiverType instanceof NeverType) {
            return [];
        }

        if (!(new ObjectType(\ReflectionMethod::class))->isSuperTypeOf($receiverType)->yes()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(self::ERROR_SET_ACCESSIBLE)
                ->identifier(self::IDENTIFIER)
                ->build(),
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveConstantStrings(Type $type): array
    {
        return array_map(
            static fn ($constantString): string => $constantString->getValue(),
            $type->getConstantStrings(),
        );
    }

    /**
     * @param list<string> $classNames
     * @param list<string> $methodNames
     *
     * @return list<RuleError>
     */
    private function buildErrorsForNonPublicTargets(array $classNames, array $methodNames): array
    {
        foreach ($classNames as $className) {
            if (!str_starts_with($className, self::OWN_NAMESPACE_PREFIX)
                || str_starts_with($className, self::TEST_SUPPORT_NAMESPACE_PREFIX)
            ) {
                continue;
            }
            if (!$this->reflectionProvider->hasClass($className)) {
                continue;
            }
            $classReflection = $this->reflectionProvider->getClass($className);

            foreach ($methodNames as $methodName) {
                if (!$classReflection->hasNativeMethod($methodName)) {
                    continue;
                }
                $method = $classReflection->getNativeMethod($methodName);
                if ($method->isPublic()) {
                    continue;
                }

                return [
                    RuleErrorBuilder::message(\sprintf(self::ERROR_NON_PUBLIC, $className, $methodName))
                        ->identifier(self::IDENTIFIER)
                        ->build(),
                ];
            }
        }

        return [];
    }
}
