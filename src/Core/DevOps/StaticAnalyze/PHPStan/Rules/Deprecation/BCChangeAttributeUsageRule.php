<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\BetterReflection\Reflection\Adapter\FakeReflectionAttribute;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionAttribute;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesAbstract;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesFinal;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Deprecation\BCChange\CallSiteCompatibilityChange;
use Shopware\Core\Framework\Deprecation\BCChange\ExtenderCompatibilityChange;
use Shopware\Core\Framework\Deprecation\BCChange\NewOptionalParameter;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterNameChange;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterRemoval;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeWidening;
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
        ParameterRemoval::class,
        ParameterTypeNarrowing::class,
    ];

    private const PARAMETER_SCOPED = [
        NewOptionalParameter::class,
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
        foreach ($node->getOriginalNode()->getMethods() as $methodNode) {
            $methodNodes[$methodNode->name->toLowerString()] = $methodNode;
        }

        $errors = [];
        foreach ($this->bcChangeAttributes($class->getAttributes()) as $attribute) {
            $errors = [...$errors, ...$this->validateCommon($attribute, $class->getShortName(), $classLine)];
            $specific = $this->validateClassLevel($attribute, $class, $classLine);
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
                if ($specific === [] && ($classIsFinal || $method->isFinal())) {
                    $subject = $classIsFinal ? 'class' : 'method';
                    $specific = $this->validateExtenderOnlyOnFinal($attribute, $symbol, $subject, $line);
                }
                if ($specific === [] && \in_array($attribute->getName(), self::RUNTIME_DETECTABLE, true)) {
                    $specific = $this->validateTriggersRuntimeDeprecation($attribute, $methodNodes[\strtolower($method->getName())] ?? null, $symbol, $line);
                }
                $errors = [...$errors, ...$specific];
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

        if (!\in_array($attributeClass, self::PARAMETER_SCOPED, true)) {
            return [];
        }

        $parameterName = $this->argument($attribute, 'parameterName', 1);
        if (!\is_string($parameterName)) {
            return [];
        }

        $parameterExists = $this->parameterExists($method, ltrim($parameterName, '$'));

        if ($attributeClass === NewOptionalParameter::class && $parameterExists) {
            return [$this->error($line, \sprintf(
                'NewOptionalParameter on "%s": parameter "%s" already exists.',
                $symbol,
                $parameterName
            ))];
        }

        if ($attributeClass !== NewOptionalParameter::class && !$parameterExists) {
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

    private function parameterExists(\ReflectionMethod $method, string $parameterName): bool
    {
        foreach ($method->getParameters() as $parameter) {
            if ($parameter->getName() === $parameterName) {
                return true;
            }
        }

        return false;
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
