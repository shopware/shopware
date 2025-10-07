<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Use_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * Detects imports from excluded namespaces that will fail with authoritative classmap.
 *
 * @internal
 *
 * @implements Rule<Use_>
 */
#[Package('framework')]
class NoComposerExcludedNamespaceRule implements Rule
{
    public function getNodeType(): string
    {
        return Use_::class;
    }

    /**
     * @param Use_ $node
     *
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->shouldSkipFile($scope)) {
            return [];
        }

        $errors = [];

        foreach ($node->uses as $use) {
            $className = $use->name->toString();

            if (!$this->isExcludedNamespace($className)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'Importing %s from excluded test namespace is forbidden.',
                $className
            ))
                ->identifier('shopware.noComposerExcludedNamespace')
                ->line($use->getStartLine())
                ->build();
        }

        return $errors;
    }

    private function shouldSkipFile(Scope $scope): bool
    {
        if ($this->isPHPStanRuleTestData($scope)) {
            return false;
        }

        if ($this->isTestFile($scope)) {
            return true;
        }

        if ($this->isPHPStanRule($scope)) {
            return true;
        }

        return false;
    }

    private function isTestFile(Scope $scope): bool
    {
        if ($this->isInTestNamespace($scope)) {
            return true;
        }

        if ($this->isInTestDirectory($scope)) {
            return true;
        }

        return false;
    }

    private function isInTestNamespace(Scope $scope): bool
    {
        $namespace = $scope->getNamespace();

        if ($namespace === null) {
            return false;
        }

        if (\str_contains($namespace, '\\Tests\\')) {
            return true;
        }

        if (\str_contains($namespace, '\\Test\\')) {
            return true;
        }

        if (\str_ends_with($namespace, '\\Test')) {
            return true;
        }

        return false;
    }

    private function isInTestDirectory(Scope $scope): bool
    {
        return \str_contains($scope->getFile(), '/tests/');
    }

    private function isPHPStanRule(Scope $scope): bool
    {
        return \str_contains($scope->getFile(), '/PHPStan/Rules/');
    }

    private function isPHPStanRuleTestData(Scope $scope): bool
    {
        return \str_contains($scope->getFile(), '/PHPStan/Rules/data/');
    }

    private function isExcludedNamespace(string $className): bool
    {
        return (bool) preg_match('/^Shopware\\\\.*\\\\Test\\\\/', $className);
    }
}
