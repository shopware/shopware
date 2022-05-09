<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Internal;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Symfony\ServiceMap;

/**
 * @implements Rule<ClassMethod>
 */
class InternalMethodRule implements Rule
{
    /**
     * @var ServiceMap
     */
    private $serviceMap;

    public function __construct(ServiceMap $symfonyServiceMap)
    {
        $this->serviceMap = $symfonyServiceMap;
    }

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param ClassMethod $node
     *
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // no class method
        if (!$scope->isInClass()) {
            return [];
        }

        // already marked as internal
        if ($this->hasInternalComment($node)) {
            return [];
        }

        $class = $scope->getClassReflection();
        // complete class is marked as internal
        if ($class !== null && $class->isInternal()) {
            return [];
        }

        if ($this->isServiceConstructor($node, $scope)) {
            return ['__construct of di container services has to be @internal'];
        }

        return [];
    }

    private function hasInternalComment(ClassMethod $node): bool
    {
        if ($node->getDocComment() === null) {
            return false;
        }

        $text = $node->getDocComment()->getText();

        return \str_contains($text, '@internal');
    }

    private function isServiceConstructor(ClassMethod $node, Scope $scope): bool
    {
        if ($this->isConstructor($node)) {
            return false;
        }

        $class = $scope->getClassReflection();
        if ($class === null) {
            return false;
        }

        // @phpstan-ignore-next-line
        $service = $this->serviceMap->getService($class->getName());

        return $service !== null;
    }

    private function isConstructor(ClassMethod $node): bool
    {
        return (string) $node->name === '__construct';
    }
}
