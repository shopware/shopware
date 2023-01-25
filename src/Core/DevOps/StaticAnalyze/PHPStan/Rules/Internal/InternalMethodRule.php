<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Internal;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Symfony\ServiceMap;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @implements Rule<ClassMethod>
 *
 * @internal
 */
#[Package('core')]
class InternalMethodRule implements Rule
{
    public function __construct(private readonly ServiceMap $serviceMap)
    {
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

        if ($this->isConstructor($node)) {
            if ($this->isEvent($scope)) {
                return [];
            }

            if ($this->isService($scope)) {
                return ['__construct of di container services has to be @internal'];
            }
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

    private function isService(Scope $scope): bool
    {
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

    private function isEvent(Scope $scope): bool
    {
        $class = $scope->getClassReflection();
        if ($class === null) {
            return false;
        }

        return $class->isSubclassOf(Event::class);
    }
}
