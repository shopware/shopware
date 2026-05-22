<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore\Errors;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore\ExemptionResolver;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore\LogicDetector;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore\SourceParser;
use Shopware\Core\Framework\Log\Package;

// Trait scanning was intentionally removed: a trait's methods are the trait's
// own coverage concern; a class carrying @codeCoverageIgnore should not be
// burdened with re-testing logic it merely composes.

/**
 * @internal
 *
 * @implements Rule<Class_>
 */
#[Package('framework')]
class CodeCoverageIgnoreEvaluationRule implements Rule
{
    private readonly ExemptionResolver $exemptions;

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
        $this->exemptions = new ExemptionResolver($reflectionProvider, new SourceParser());
    }

    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @param Class_ $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $classHasIgnore = $this->docHasCodeCoverageIgnore($node);
        $className = $this->className($node);

        if (!$classHasIgnore && !$this->anyMethodHasIgnore($node)) {
            return [];
        }

        if ($classHasIgnore && $this->isThrowable($node, $className)) {
            return [Errors::exception($className, $node->getStartLine())];
        }

        $classExempted = $classHasIgnore && $this->exemptions->isExempted($node, $scope);

        return $this->checkMethods($node, $scope, $className, $classHasIgnore, $classExempted);
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
     * @return list<IdentifierRuleError>
     */
    private function checkMethods(
        Class_ $node,
        Scope $scope,
        string $className,
        bool $classHasIgnore,
        bool $classExempted,
    ): array {
        $errors = [];

        foreach ($node->getMethods() as $method) {
            $methodName = (string) $method->name;

            if ($classHasIgnore && !$classExempted && LogicDetector::methodContainsLogic($method)) {
                $errors[] = Errors::classLevel($className, $methodName, $method->getStartLine());

                continue;
            }

            if (!$this->docHasCodeCoverageIgnore($method)) {
                continue;
            }

            if ($this->exemptions->isExempted($method, $scope)) {
                continue;
            }

            if (LogicDetector::methodContainsLogic($method)) {
                $errors[] = Errors::methodLevel($className, $methodName, $method->getStartLine());
            }
        }

        return $errors;
    }

    private function isThrowable(Class_ $node, string $className): bool
    {
        if ($node->extends === null) {
            return false;
        }

        if (!$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        return $this->reflectionProvider->getClass($className)
            ->getNativeReflection()
            ->isSubclassOf(\Throwable::class);
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
}
