<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore\Errors;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore\LogicDetector;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore\SourceParser;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @implements Rule<Class_>
 */
#[Package('framework')]
class CodeCoverageIgnoreEvaluationRule implements Rule
{
    private readonly SourceParser $sources;

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
        $this->sources = new SourceParser($reflectionProvider);
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

        $classExempted = $classHasIgnore && $this->hasSeeIntegrationTest($node, $scope);

        return [
            ...$this->checkMethods($node, $scope, $className, $classHasIgnore, $classExempted),
            ...$this->checkTraitMethods($node, $className, $classHasIgnore, $classExempted),
        ];
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

            if ($this->hasSeeIntegrationTest($method, $scope)) {
                continue;
            }

            if (LogicDetector::methodContainsLogic($method)) {
                $errors[] = Errors::methodLevel($className, $methodName, $method->getStartLine());
            }
        }

        return $errors;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function checkTraitMethods(
        Class_ $node,
        string $className,
        bool $classHasIgnore,
        bool $classExempted,
    ): array {
        if (!$classHasIgnore || $classExempted) {
            return [];
        }

        $errors = [];

        foreach ($node->getTraitUses() as $use) {
            foreach ($use->traits as $traitName) {
                $name = $traitName->toString();
                foreach ($this->sources->traitMethods($name) as $method) {
                    if (!LogicDetector::methodContainsLogic($method)) {
                        continue;
                    }

                    $errors[] = Errors::traitMethod(
                        $className,
                        $name,
                        (string) $method->name,
                        $node->getStartLine(),
                    );
                }
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

    private function hasSeeIntegrationTest(Node $node, Scope $scope): bool
    {
        $doc = $node->getDocComment();
        if ($doc === null) {
            return false;
        }

        if (!preg_match_all('/@see\s+(\S+)/', $doc->getText(), $matches)) {
            return false;
        }

        $useMap = null;

        foreach ($matches[1] as $reference) {
            $rawClass = explode('::', $reference)[0];
            $candidate = ltrim($rawClass, '\\');
            if ($candidate === '') {
                continue;
            }

            $resolved = $candidate;

            // Unqualified (no `\`) references are resolved against the file's
            // use statements. Qualified refs (with `\` or relative path) are
            // taken as-is, matching common phpdoc conventions in this codebase.
            if (!str_starts_with($rawClass, '\\') && !str_contains($candidate, '\\')) {
                $useMap ??= $this->sources->useMap($scope->getFile());
                $resolved = $useMap[$candidate] ?? $candidate;
            }

            if (!str_contains($resolved, '\\Tests\\Integration\\')) {
                continue;
            }

            if ($this->reflectionProvider->hasClass($resolved)) {
                return true;
            }
        }

        return false;
    }

    private function className(Class_ $node): string
    {
        if ($node->namespacedName !== null) {
            return $node->namespacedName->toString();
        }

        return $node->name?->name ?? '<anonymous class>';
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
