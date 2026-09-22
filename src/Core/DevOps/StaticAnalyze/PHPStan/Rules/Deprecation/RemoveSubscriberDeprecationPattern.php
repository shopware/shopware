<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation;

use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use Shopware\Core\Framework\Log\Package;

/**
 * Subscribers still need to be called for BC reasons, therefore they do not trigger deprecations.
 *
 * @internal
 */
#[Package('framework')]
class RemoveSubscriberDeprecationPattern implements DeprecationPattern
{
    public function isSupported(ClassMethod $method, Scope $scope, ClassReflection $class, string $deprecation, bool $isClassDeprecation): bool
    {
        return \str_contains($deprecation, 'reason:remove-subscriber');
    }

    public function check(ClassMethod $method, Scope $scope, ClassReflection $class, string $deprecation, bool $isClassDeprecation): array
    {
        return [];
    }
}
