<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\WebhookException;

/**
 * @implements Rule<ClassMethod>
 *
 * @internal
 */
#[Package('framework')]
class NoRuntimeExceptionInDomainExceptionsRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param ClassMethod $node
     *
     * @return array<int, \PHPStan\Rules\RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Only care about static methods
        if (!$node instanceof ClassMethod || !$node->isStatic()) {
            return [];
        }

        // Not inside a class
        if (!$scope->isInClass()) {
            return [];
        }

        $classReflection = $scope->getClassReflection();

        // Only classes that extend Shopware\Core\Framework\HttpException
        if (!$classReflection->is(HttpException::class)) {
            return [];
        }

        // No declared return type -> nothing to check
        if ($node->returnType === null) {
            return [];
        }

        $method = $classReflection->getMethod($node->name->name, $scope);
        foreach ($method->getVariants() as $variant) {
            if ($variant->getReturnType()->isSuperTypeOf(new ObjectType(\RuntimeException::class))->yes()) {
                /** @deprecated tag:v6.8.0 - known violations will be fixed with 6.8.0 so this if can be removed */
                if ($this->isAKnownViolation($method, $classReflection)) {
                    return [];
                }

                return [
                    RuleErrorBuilder::message(
                        \sprintf(
                            'Domain exception factory method %s::%s() might return \RuntimeException, however the ExceptionClass itself already extends \RuntimeException, therefore it should only return self.',
                            $classReflection->getName(),
                            $node->name,
                        )
                    )->identifier('shopware.noRuntimeExceptionInDomainExceptions')->line($node->getStartLine())
                        ->build(),
                ];
            }
        }

        return [];
    }

    private function isAKnownViolation(ExtendedMethodReflection $method, ClassReflection $classReflection): bool
    {
        // List of known violations that are allowed to return RuntimeException for now
        // All of those already only return the specific domain exception in reality
        // However changing the return type would be a breaking change, so all of them are marked as deprecated
        // and RuntimeException return type will be removed in 6.8.0.0
        $knownViolations = [
            DataAbstractionLayerException::cannotBuildAccessor(...),
            DataAbstractionLayerException::onlyStorageAwareFieldsAsTranslated(...),
            DataAbstractionLayerException::onlyStorageAwareFieldsInReadCondition(...),
            DataAbstractionLayerException::primaryKeyNotStorageAware(...),
            DataAbstractionLayerException::missingTranslatedStorageAwareProperty(...),
            DataAbstractionLayerException::noTranslationDefinition(...),
            DataAbstractionLayerException::missingVersionField(...),
            DataAbstractionLayerException::unexpectedFieldType(...),
            WebhookException::invalidDataMapping(...),
            WebhookException::unknownEventDataType(...),
        ];

        $methodName = $method->getName();
        /** @phpstan-ignore staticMethod.dynamicName */
        $methodCallable = $classReflection->getName()::$methodName(...);

        /** @phpstan-ignore function.strict (the compare of callables only works with strict = false) */
        return \in_array($methodCallable, $knownViolations, false);
    }
}
