<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore\Errors;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore\ExemptionResolver;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore\LogicDetector;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore\UseMap;
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
    private readonly ExemptionResolver $exemptions;

    public function __construct(ReflectionProvider $reflectionProvider)
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

        return $this->checkMethods($node, $useMap, $className, $classHasIgnore, $classExempted);
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

            if ($this->exemptions->isExempted($method, $useMap)) {
                continue;
            }

            if (LogicDetector::methodContainsLogic($method)) {
                $errors[] = Errors::methodLevel($className, $methodName, $method->getStartLine());
            }
        }

        return $errors;
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
