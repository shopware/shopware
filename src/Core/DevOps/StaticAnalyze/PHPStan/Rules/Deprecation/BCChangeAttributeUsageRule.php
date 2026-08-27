<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\BetterReflection\Reflection\Adapter\FakeReflectionAttribute;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionAttribute;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesAbstract;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesFinal;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesReadonly;
use Shopware\Core\Framework\Deprecation\BCChange\CallSiteCompatibilityChange;
use Shopware\Core\Framework\Deprecation\BCChange\ClassHierarchyChange;
use Shopware\Core\Framework\Deprecation\BCChange\ExceptionChange;
use Shopware\Core\Framework\Deprecation\BCChange\ExtenderCompatibilityChange;
use Shopware\Core\Framework\Deprecation\BCChange\NewOptionalParameter;
use Shopware\Core\Framework\Deprecation\BCChange\NewRequiredParameter;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterDefaultValueChange;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterNameChange;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterRemoval;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeWidening;
use Shopware\Core\Framework\Deprecation\BCChange\PropertyTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\VisibilityChange;
use Shopware\Core\Framework\Log\Package;

/**
 * Validates that BC-change attributes announce changes that are structurally possible:
 * the version has a valid format, referenced parameters exist (or, for new parameters,
 * do not exist yet), the announced state differs from the current declaration, and
 * extender-only changes are not announced on final symbols, where no extenders can
 * exist and the change can be applied directly.
 *
 * Announced changes whose legacy usage is detectable at runtime must trigger a
 * conditional runtime deprecation via `Feature::triggerDeprecationOrThrow()`.
 *
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class BCChangeAttributeUsageRule implements Rule
{
    private const VERSION_PATTERN = '/^v\d+\.\d+\.\d+$/';

    private const BC_CHANGE_NAMESPACE_PREFIX = 'Shopware\\Core\\Framework\\Deprecation\\BCChange\\';

    /**
     * Attributes whose legacy usage is detectable at runtime: a narrowed parameter can
     * check the passed value, and the default implementation of a method becoming
     * abstract only runs when a subclass still relies on it.
     */
    private const RUNTIME_DETECTABLE = [
        BecomesAbstract::class,
        NewRequiredParameter::class,
        ParameterRemoval::class,
        ParameterTypeNarrowing::class,
    ];

    /**
     * Methods carrying these attributes are invoked by the framework with exactly the
     * declared parameters, so a runtime trigger would fire on every legitimate request.
     */
    private const FRAMEWORK_INVOKED_ATTRIBUTES = [
        'Symfony\Component\Routing\Attribute\Route',
        'Symfony\Component\Routing\Annotation\Route',
    ];

    /**
     * These attributes announce a parameter that will only be added in the next major.
     * The named parameter must therefore not be part of the current method signature.
     */
    private const NEW_PARAMETER_ATTRIBUTES = [
        NewOptionalParameter::class,
        NewRequiredParameter::class,
    ];

    private const PARAMETER_SCOPED = [
        NewOptionalParameter::class,
        NewRequiredParameter::class,
        ParameterDefaultValueChange::class,
        ParameterNameChange::class,
        ParameterRemoval::class,
        ParameterTypeNarrowing::class,
        ParameterTypeWidening::class,
    ];

    public function __construct(private readonly ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection()->getNativeReflection();
        $classLine = $node->getOriginalNode()->getStartLine();
        $classIsFinal = $class->isFinal() || \str_contains((string) $class->getDocComment(), '@final');

        $methodNodes = [];
        $propertyNodes = [];
        foreach ($node->getOriginalNode()->getMethods() as $methodNode) {
            $methodNodes[$methodNode->name->toLowerString()] = $methodNode;

            if ($methodNode->name->toLowerString() !== '__construct') {
                continue;
            }

            foreach ($methodNode->params as $parameter) {
                if ($parameter->flags === 0 || !$parameter->var instanceof Variable || !\is_string($parameter->var->name)) {
                    continue;
                }

                $propertyNodes[\strtolower($parameter->var->name)] = $parameter;
            }
        }

        foreach ($node->getOriginalNode()->getProperties() as $propertyNode) {
            foreach ($propertyNode->props as $property) {
                $propertyNodes[$property->name->toLowerString()] = $propertyNode;
            }
        }

        $errors = [];
        foreach ($this->bcChangeAttributes($class->getAttributes()) as $attribute) {
            $errors = [...$errors, ...$this->validateCommon($attribute, $class->getShortName(), $classLine)];
            $specific = $this->validateClassLevel($attribute, $class, $classLine);
            if ($specific === [] && $attribute->getName() === ClassHierarchyChange::class) {
                $specific = $this->validateClassHierarchyChange($attribute, $node->getClassReflection(), $methodNodes, $classLine);
            }
            if ($specific === [] && $classIsFinal) {
                $specific = $this->validateExtenderOnlyOnFinal($attribute, $class->getShortName(), 'class', $classLine);
            }
            $errors = [...$errors, ...$specific];
        }

        foreach ($class->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }

            $symbol = \sprintf('%s::%s()', $class->getShortName(), $method->getName());
            $line = $method->getStartLine() ?: $classLine;
            foreach ($this->bcChangeAttributes($method->getAttributes()) as $attribute) {
                $errors = [...$errors, ...$this->validateCommon($attribute, $symbol, $line)];
                $specific = $this->validateMethodLevel($attribute, $method, $symbol, $line);
                if ($specific === [] && $attribute->getName() === ExceptionChange::class) {
                    $specific = $this->validateExceptionChange($attribute, $node->getClassReflection(), $method->getName(), $symbol, $line);
                }
                if ($specific === [] && ($classIsFinal || $method->isFinal())) {
                    $subject = $classIsFinal ? 'class' : 'method';
                    $specific = $this->validateExtenderOnlyOnFinal($attribute, $symbol, $subject, $line);
                }
                if ($specific === [] && \in_array($attribute->getName(), self::RUNTIME_DETECTABLE, true) && !$this->isFrameworkInvoked($method)) {
                    $specific = $this->validateTriggersRuntimeDeprecation($attribute, $methodNodes[\strtolower($method->getName())] ?? null, $symbol, $line);
                }
                $errors = [...$errors, ...$specific];
            }
        }

        foreach ($class->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }

            $symbol = \sprintf('%s::$%s', $class->getShortName(), $property->getName());
            $line = ($propertyNodes[\strtolower($property->getName())] ?? null)?->getStartLine() ?? $classLine;
            foreach ($this->bcChangeAttributes($property->getAttributes()) as $attribute) {
                $errors = [...$errors, ...$this->validateCommon($attribute, $symbol, $line)];
                $errors = [...$errors, ...$this->validatePropertyLevel($attribute, $property, $symbol, $line)];
            }
        }

        return $errors;
    }

    /**
     * @param list<ReflectionAttribute|FakeReflectionAttribute> $attributes
     *
     * @return list<ReflectionAttribute|FakeReflectionAttribute>
     */
    private function bcChangeAttributes(array $attributes): array
    {
        $matches = [];
        foreach ($attributes as $attribute) {
            if (\str_starts_with($attribute->getName(), self::BC_CHANGE_NAMESPACE_PREFIX)) {
                $matches[] = $attribute;
            }
        }

        return $matches;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validateCommon(ReflectionAttribute|FakeReflectionAttribute $attribute, string $symbol, int $line): array
    {
        $errors = [];

        $version = $this->argument($attribute, 'version', 0);
        if (!\is_string($version) || preg_match(self::VERSION_PATTERN, $version) !== 1) {
            $errors[] = $this->error($line, \sprintf(
                '%s on "%s": version "%s" must match the format "v6.8.0".',
                $this->shortName($attribute),
                $symbol,
                \is_scalar($version) ? (string) $version : \gettype($version)
            ));
        }

        if (\in_array($attribute->getName(), self::PARAMETER_SCOPED, true)) {
            $parameterName = $this->argument($attribute, 'parameterName', 1);
            if (\is_string($parameterName) && \str_starts_with($parameterName, '$')) {
                $errors[] = $this->error($line, \sprintf(
                    '%s on "%s": parameter name "%s" must be given without the leading "$".',
                    $this->shortName($attribute),
                    $symbol,
                    $parameterName
                ));
            }
        }

        return $errors;
    }

    /**
     * @param \ReflectionClass<object> $class
     *
     * @return list<IdentifierRuleError>
     */
    private function validateClassLevel(ReflectionAttribute|FakeReflectionAttribute $attribute, \ReflectionClass $class, int $line): array
    {
        $symbol = $class->getShortName();

        if ($attribute->getName() === BecomesFinal::class && $class->isFinal()) {
            return [$this->error($line, \sprintf('BecomesFinal on "%s": the class is already final.', $symbol))];
        }

        if ($attribute->getName() === BecomesInternal::class && $this->isMarkedInternal($class->getDocComment())) {
            return [$this->error($line, \sprintf('BecomesInternal on "%s": the class is already @internal.', $symbol))];
        }

        return [];
    }

    /**
     * @param array<string, ClassMethod> $methodNodes
     *
     * @return list<IdentifierRuleError>
     */
    private function validateClassHierarchyChange(ReflectionAttribute|FakeReflectionAttribute $attribute, ClassReflection $class, array $methodNodes, int $line): array
    {
        $newParentClass = $this->argument($attribute, 'newParentClass', 2);
        $newParentMethods = [];
        if (\is_string($newParentClass) && $this->reflectionProvider->hasClass($newParentClass)) {
            foreach ($this->reflectionProvider->getClass($newParentClass)->getNativeReflection()->getMethods() as $method) {
                if ($method->isPrivate()) {
                    continue;
                }

                $newParentMethods[strtolower($method->getName())] = true;
            }
        }

        $removedParentMethods = [];
        for ($parent = $class->getParentClass(); $parent !== null; $parent = $parent->getParentClass()) {
            if (\is_string($newParentClass) && $this->isInHierarchy($parent, $newParentClass)) {
                continue;
            }

            foreach ($parent->getNativeReflection()->getMethods() as $method) {
                if ($method->isPrivate() || $method->getDeclaringClass()->getName() !== $parent->getName()) {
                    continue;
                }

                $removedParentMethods[strtolower($method->getName())] ??= $method;
            }
        }

        $errors = [];
        foreach ($removedParentMethods as $methodName => $parentMethod) {
            if (str_starts_with($parentMethod->getName(), '__') || $this->isDeprecated($parentMethod) || isset($newParentMethods[$methodName])) {
                continue;
            }

            if ($this->isDeclaredByClass($class, $methodNodes, $methodName)) {
                continue;
            }

            $errors[] = $this->error($line, \sprintf(
                'ClassHierarchyChange on "%s": inherited method "%s()" from "%s" will be removed from the hierarchy. Override it explicitly and mark the override as deprecated, unless the new parent also provides the method.',
                $this->shortClassName($class->getName()),
                $parentMethod->getName(),
                $parentMethod->getDeclaringClass()->getShortName()
            ));
        }

        return $errors;
    }

    /**
     * @param array<string, ClassMethod> $methodNodes
     */
    private function isDeclaredByClass(ClassReflection $class, array $methodNodes, string $methodName): bool
    {
        if (isset($methodNodes[$methodName])) {
            return true;
        }

        foreach ($class->getTraits() as $trait) {
            if ($trait->hasNativeMethod($methodName)
                && $trait->getNativeMethod($methodName)->getDeclaringClass()->getName() === $trait->getName()) {
                return true;
            }
        }

        return false;
    }

    private function isInHierarchy(ClassReflection $class, string $possibleDescendant): bool
    {
        if (!$this->reflectionProvider->hasClass($possibleDescendant)) {
            return false;
        }

        for ($ancestor = $this->reflectionProvider->getClass($possibleDescendant); $ancestor !== null; $ancestor = $ancestor->getParentClass()) {
            if ($ancestor->getName() === $class->getName()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validateMethodLevel(ReflectionAttribute|FakeReflectionAttribute $attribute, \ReflectionMethod $method, string $symbol, int $line): array
    {
        $attributeClass = $attribute->getName();

        if ($attributeClass === BecomesAbstract::class && $method->isAbstract()) {
            return [$this->error($line, \sprintf('BecomesAbstract on "%s": the method is already abstract.', $symbol))];
        }

        if ($attributeClass === BecomesInternal::class && $this->isMarkedInternal($method->getDocComment())) {
            return [$this->error($line, \sprintf('BecomesInternal on "%s": the method is already @internal.', $symbol))];
        }

        if ($attributeClass === VisibilityChange::class) {
            $newVisibility = $this->argument($attribute, 'newVisibility', 1);
            if ($newVisibility === 'protected' && !$method->isPublic()) {
                return [$this->error($line, \sprintf(
                    'VisibilityChange on "%s": announced visibility "protected" is not narrower than the current visibility.',
                    $symbol
                ))];
            }
            if ($newVisibility === 'private' && $method->isPrivate()) {
                return [$this->error($line, \sprintf('VisibilityChange on "%s": the method is already private.', $symbol))];
            }
        }

        if ($attributeClass === ParameterDefaultValueChange::class) {
            return $this->validateParameterDefaultValueChange($attribute, $method, $symbol, $line);
        }

        if ($attributeClass === ParameterRemoval::class) {
            return $this->validateParameterRemoval($attribute, $method, $symbol, $line);
        }

        if (!\in_array($attributeClass, self::PARAMETER_SCOPED, true)) {
            return [];
        }

        $parameterName = $this->argument($attribute, 'parameterName', 1);
        if (!\is_string($parameterName)) {
            return [];
        }

        $parameterExists = $this->parameterExists($method, \ltrim($parameterName, '$'));

        if (\in_array($attributeClass, self::NEW_PARAMETER_ATTRIBUTES, true) && $parameterExists) {
            return [$this->error($line, \sprintf(
                '%s on "%s": parameter "%s" already exists.',
                $this->shortName($attribute),
                $symbol,
                $parameterName
            ))];
        }

        if (!\in_array($attributeClass, self::NEW_PARAMETER_ATTRIBUTES, true) && !$parameterExists) {
            return [$this->error($line, \sprintf(
                '%s on "%s": parameter "%s" does not exist.',
                $this->shortName($attribute),
                $symbol,
                $parameterName
            ))];
        }

        return [];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validatePropertyLevel(ReflectionAttribute|FakeReflectionAttribute $attribute, \ReflectionProperty $property, string $symbol, int $line): array
    {
        if ($attribute->getName() === BecomesReadonly::class && $property->isReadOnly()) {
            return [$this->error($line, \sprintf('BecomesReadonly on "%s": the property is already readonly.', $symbol))];
        }

        if ($attribute->getName() === PropertyTypeNarrowing::class) {
            $newType = $this->argument($attribute, 'newType', 1);
            if (!\is_string($newType)) {
                return [];
            }

            $currentType = $property->getType();
            if ($currentType instanceof \ReflectionNamedType
                && ($currentType->allowsNull() ? '?' : '') . $currentType->getName() === $newType
            ) {
                return [$this->error($line, \sprintf('PropertyTypeNarrowing on "%s": announced type "%s" is identical to the current property type.', $symbol, $newType))];
            }
        }

        if ($attribute->getName() !== VisibilityChange::class) {
            return [];
        }

        $newVisibility = $this->argument($attribute, 'newVisibility', 1);
        if ($newVisibility === 'protected' && !$property->isPublic()) {
            return [$this->error($line, \sprintf(
                'VisibilityChange on "%s": announced visibility "protected" is not narrower than the current visibility.',
                $symbol
            ))];
        }
        if ($newVisibility === 'private' && $property->isPrivate()) {
            return [$this->error($line, \sprintf('VisibilityChange on "%s": the property is already private.', $symbol))];
        }

        return [];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validateParameterDefaultValueChange(ReflectionAttribute|FakeReflectionAttribute $attribute, \ReflectionMethod $method, string $symbol, int $line): array
    {
        $parameterName = $this->argument($attribute, 'parameterName', 1);
        if (!\is_string($parameterName)) {
            return [];
        }

        $parameter = $this->parameter($method, \ltrim($parameterName, '$'));
        if ($parameter === null) {
            return [$this->error($line, \sprintf(
                '%s on "%s": parameter "%s" does not exist.',
                $this->shortName($attribute),
                $symbol,
                $parameterName
            ))];
        }

        if (!$parameter->isOptional() || !$parameter->isDefaultValueAvailable()) {
            return [$this->error($line, \sprintf(
                '%s on "%s": parameter "%s" has no current default value.',
                $this->shortName($attribute),
                $symbol,
                $parameterName
            ))];
        }

        $newDefaultValue = $this->argument($attribute, 'newDefaultValue', 2);
        if ($parameter->getDefaultValue() === $newDefaultValue) {
            return [$this->error($line, \sprintf(
                '%s on "%s": announced default value for parameter "%s" is already current.',
                $this->shortName($attribute),
                $symbol,
                $parameterName
            ))];
        }

        return [];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validateParameterRemoval(ReflectionAttribute|FakeReflectionAttribute $attribute, \ReflectionMethod $method, string $symbol, int $line): array
    {
        $parameterName = $this->argument($attribute, 'parameterName', 1);
        if (!\is_string($parameterName)) {
            return [];
        }

        $parameter = $this->parameter($method, $parameterName);
        if ($parameter === null) {
            return [$this->error($line, \sprintf(
                'ParameterRemoval on "%s": parameter "%s" does not exist.',
                $symbol,
                $parameterName
            ))];
        }

        if (!$parameter->isOptional()) {
            return [$this->error($line, \sprintf(
                'ParameterRemoval on "%s": parameter "%s" is required. Removing a required parameter is not actionable before the major release; introduce a new method or factory with the future signature and deprecate the old method instead.',
                $symbol,
                $parameterName
            ))];
        }

        return [];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validateExtenderOnlyOnFinal(ReflectionAttribute|FakeReflectionAttribute $attribute, string $symbol, string $subject, int $line): array
    {
        if (!$this->reflectionProvider->hasClass($attribute->getName())) {
            return [];
        }

        $attributeClass = $this->reflectionProvider->getClass($attribute->getName());
        if (!$attributeClass->implementsInterface(ExtenderCompatibilityChange::class)
            || $attributeClass->implementsInterface(CallSiteCompatibilityChange::class)) {
            return [];
        }

        return [$this->error($line, \sprintf(
            '%s on "%s": the %s is final, so no extenders can exist. Apply the announced change directly instead of announcing it.',
            $this->shortName($attribute),
            $symbol,
            $subject
        ))];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validateTriggersRuntimeDeprecation(ReflectionAttribute|FakeReflectionAttribute $attribute, ?ClassMethod $methodNode, string $symbol, int $line): array
    {
        if ($methodNode === null || $methodNode->stmts === null) {
            // abstract and interface methods have no body that could trigger
            return [];
        }

        $trigger = (new NodeFinder())->findFirst(
            $methodNode->stmts,
            static fn (Node $n): bool => $n instanceof StaticCall
                && $n->name instanceof Identifier
                && $n->name->toString() === 'triggerDeprecationOrThrow'
        );
        if ($trigger !== null) {
            return [];
        }

        return [$this->error($line, \sprintf(
            '%s on "%s": the legacy usage is detectable at runtime, but the method does not call "Feature::triggerDeprecationOrThrow". Trigger a deprecation when the method is used in a way that breaks with the announced change.',
            $this->shortName($attribute),
            $symbol
        ))];
    }

    private function isFrameworkInvoked(\ReflectionMethod $method): bool
    {
        foreach ($method->getAttributes() as $attribute) {
            if (\in_array($attribute->getName(), self::FRAMEWORK_INVOKED_ATTRIBUTES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validateExceptionChange(ReflectionAttribute|FakeReflectionAttribute $attribute, ClassReflection $class, string $methodName, string $symbol, int $line): array
    {
        $announced = $this->argument($attribute, 'newExceptions', 1);
        if (!\is_array($announced)) {
            return [$this->error($line, \sprintf(
                'ExceptionChange on "%s": "newExceptions" must be an array of exception classes.',
                $symbol
            ))];
        }

        if ($announced === []) {
            return [];
        }

        $announcedTypes = [];
        foreach ($announced as $exceptionClass) {
            if (!\is_string($exceptionClass) || !$this->reflectionProvider->hasClass($exceptionClass)) {
                return [$this->error($line, \sprintf(
                    'ExceptionChange on "%s": announced exception "%s" is not a resolvable class. Reference exception classes via ::class.',
                    $symbol,
                    \is_scalar($exceptionClass) ? (string) $exceptionClass : \gettype($exceptionClass)
                ))];
            }
            if (!$this->reflectionProvider->getClass($exceptionClass)->implementsInterface(\Throwable::class)) {
                return [$this->error($line, \sprintf(
                    'ExceptionChange on "%s": announced class "%s" is not a Throwable.',
                    $symbol,
                    $exceptionClass
                ))];
            }
            $announcedTypes[] = new ObjectType($exceptionClass);
        }

        if (!$class->hasNativeMethod($methodName)) {
            return [];
        }

        $throwType = $class->getNativeMethod($methodName)->getThrowType();
        if ($throwType === null) {
            // no documented @throws contract to compare against
            return [];
        }

        foreach ($announcedTypes as $announcedType) {
            if (!$throwType->isSuperTypeOf($announcedType)->yes()) {
                // at least one announced exception falls outside the current contract - a real change
                return [];
            }
        }

        return [$this->error($line, \sprintf(
            'ExceptionChange on "%s": every announced exception is already covered by the current "@throws" contract. Throwing narrower exceptions is not a BC change; apply it directly instead of announcing it.',
            $symbol
        ))];
    }

    private function parameterExists(\ReflectionMethod $method, string $parameterName): bool
    {
        return $this->parameter($method, $parameterName) !== null;
    }

    private function parameter(\ReflectionMethod $method, string $parameterName): ?\ReflectionParameter
    {
        foreach ($method->getParameters() as $parameter) {
            if ($parameter->getName() === $parameterName) {
                return $parameter;
            }
        }

        return null;
    }

    private function argument(ReflectionAttribute|FakeReflectionAttribute $attribute, string $name, int $position): mixed
    {
        $arguments = $attribute->getArguments();

        return $arguments[$name] ?? $arguments[$position] ?? null;
    }

    private function isMarkedInternal(string|false $doc): bool
    {
        return \is_string($doc) && \str_contains($doc, '@internal');
    }

    private function isDeprecated(\ReflectionMethod $method): bool
    {
        return $method->isDeprecated() || (\is_string($method->getDocComment()) && \str_contains($method->getDocComment(), '@deprecated'));
    }

    private function shortClassName(string $className): string
    {
        $parts = explode('\\', $className);

        return end($parts);
    }

    private function shortName(ReflectionAttribute|FakeReflectionAttribute $attribute): string
    {
        $parts = explode('\\', $attribute->getName());

        return end($parts);
    }

    private function error(int $line, string $message): IdentifierRuleError
    {
        return RuleErrorBuilder::message($message)
            ->identifier('shopware.bcChangeAttribute')
            ->line($line)
            ->build();
    }
}
