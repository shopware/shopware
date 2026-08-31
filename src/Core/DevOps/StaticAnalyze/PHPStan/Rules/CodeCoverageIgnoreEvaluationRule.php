<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore\Errors;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore\ExemptionResolver;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore\LogicDetector;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore\UseMap;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\Log\Package;

// Trait scanning was intentionally removed: a trait's methods are the trait's
// own coverage concern; a class carrying @codeCoverageIgnore should not be
// burdened with re-testing logic it merely composes.

/**
 * Registered on Namespace_ rather than Class_ so the file's `use` statements are
 * available in the same pass: short-form @see references are resolved against the
 * use map (see UseMap) without re-reading the source from disk.
 *
 * @internal
 *
 * @implements Rule<Namespace_>
 */
#[Package('framework')]
class CodeCoverageIgnoreEvaluationRule implements Rule
{
    private const NOT_CONSTANT = '__not_constant__';

    private readonly ExemptionResolver $exemptions;

    public function __construct(private readonly ReflectionProvider $reflectionProvider)
    {
        $this->exemptions = new ExemptionResolver($reflectionProvider);
    }

    public function getNodeType(): string
    {
        return Namespace_::class;
    }

    /**
     * @param Namespace_ $node
     *
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $useMap = UseMap::fromStmts($node->stmts);

        $errors = [];
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Class_) {
                $errors = [...$errors, ...$this->evaluateClass($stmt, $useMap)];
            }
        }

        return $errors;
    }

    /**
     * @param array<string, string> $useMap
     *
     * @return list<IdentifierRuleError>
     */
    private function evaluateClass(Class_ $node, array $useMap): array
    {
        $classHasIgnore = $this->docHasCodeCoverageIgnore($node);
        $className = $this->className($node);

        if (!$classHasIgnore && !$this->anyMethodHasIgnore($node)) {
            return [];
        }

        $classExempted = $classHasIgnore && $this->exemptions->isExempted($node, $useMap);

        return $this->checkMethods($node, $useMap, $className, $classHasIgnore, $classExempted, $this->isThrowable($className), $this->declaresSchema($className));
    }

    private function anyMethodHasIgnore(Class_ $node): bool
    {
        foreach ($node->getMethods() as $method) {
            if ($this->docHasCodeCoverageIgnore($method)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string> $useMap
     *
     * @return list<IdentifierRuleError>
     */
    private function checkMethods(
        Class_ $node,
        array $useMap,
        string $className,
        bool $classHasIgnore,
        bool $classExempted,
        bool $inThrowableContext,
        bool $declaresSchema,
    ): array {
        $errors = [];

        foreach ($node->getMethods() as $method) {
            $methodName = (string) $method->name;

            if ($classHasIgnore && !$classExempted && (LogicDetector::methodContainsLogic($method, $inThrowableContext, $declaresSchema) || $this->redefinesAParentDefault($className, $method, $inThrowableContext))) {
                $errors[] = Errors::classLevel($className, $methodName, $method->getStartLine());

                continue;
            }

            if (!$this->docHasCodeCoverageIgnore($method)) {
                continue;
            }

            if ($this->exemptions->isExempted($method, $useMap)) {
                continue;
            }

            if (LogicDetector::methodContainsLogic($method, $inThrowableContext, $declaresSchema) || $this->redefinesAParentDefault($className, $method, $inThrowableContext)) {
                $errors[] = Errors::methodLevel($className, $methodName, $method->getStartLine());
            }
        }

        return $errors;
    }

    /**
     * A constructor whose parameter carries a default the parent constructor does not
     * have, or a different one (`int $maxLength = 64` over the parent's `255`), turns the
     * subclass into configuration: the value is the class's reason to exist and deserves
     * a test. Parameters are matched by name against the parent constructor; parameters the
     * parent does not have and non-constant defaults are left alone.
     */
    private function redefinesAParentDefault(string $className, ClassMethod $method, bool $inThrowableContext): bool
    {
        if ($inThrowableContext || $method->name->toLowerString() !== '__construct' || !$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        $parent = $this->reflectionProvider->getClass($className)->getParentClass();
        if ($parent === null || !$parent->hasConstructor()) {
            return false;
        }

        $parentParameters = [];
        foreach ($parent->getConstructor()->getVariants()[0]->getParameters() as $parentParameter) {
            $parentParameters[$parentParameter->getName()] = $parentParameter;
        }

        foreach ($method->params as $param) {
            if ($param->default === null || !$param->var instanceof Node\Expr\Variable || !\is_string($param->var->name) || !isset($parentParameters[$param->var->name])) {
                continue;
            }

            $childDefault = $this->constantValue($param->default);
            if ($childDefault === self::NOT_CONSTANT) {
                continue;
            }

            $parentDefault = $parentParameters[$param->var->name]->getDefaultValue();
            if ($parentDefault === null) {
                return true;
            }

            $parentValues = $parentDefault->getConstantScalarValues();
            if (\count($parentValues) !== 1) {
                continue;
            }

            if ($parentValues[0] !== $childDefault) {
                return true;
            }
        }

        return false;
    }

    private function constantValue(Node\Expr $expr): mixed
    {
        if ($expr instanceof Node\Scalar\Int_ || $expr instanceof Node\Scalar\Float_ || $expr instanceof Node\Scalar\String_) {
            return $expr->value;
        }

        if ($expr instanceof Node\Expr\UnaryMinus) {
            $inner = $this->constantValue($expr->expr);

            return \is_int($inner) || \is_float($inner) ? -$inner : self::NOT_CONSTANT;
        }

        if ($expr instanceof Node\Expr\ConstFetch) {
            return match ($expr->name->toLowerString()) {
                'true' => true,
                'false' => false,
                'null' => null,
                default => self::NOT_CONSTANT,
            };
        }

        return self::NOT_CONSTANT;
    }

    private function className(Class_ $node): string
    {
        if ($node->namespacedName !== null) {
            return $node->namespacedName->toString();
        }

        if ($node->name === null) {
            return '<anonymous class>';
        }

        return $node->name->name;
    }

    private function docHasCodeCoverageIgnore(Node $node): bool
    {
        $doc = $node->getDocComment();
        if ($doc === null) {
            return false;
        }

        return (bool) preg_match('/@codeCoverageIgnore(?![A-Za-z])/', $doc->getText());
    }

    /**
     * Entity extensions declare schema by adding to the collections handed to them; that is
     * the same declarative content a definition returns from `defineFields()`, which the rule
     * accepts, so the two are treated alike.
     */
    private function declaresSchema(string $className): bool
    {
        if (!$this->reflectionProvider->hasClass($className) || !$this->reflectionProvider->hasClass(EntityExtension::class)) {
            return false;
        }

        return $this->reflectionProvider->getClass($className)->isSubclassOfClass($this->reflectionProvider->getClass(EntityExtension::class));
    }

    private function isThrowable(string $className): bool
    {
        if (!$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        return $this->reflectionProvider->getClass($className)->implementsInterface(\Throwable::class);
    }
}
