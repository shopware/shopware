<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation;

use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface DeprecationPattern
{
    public function isSupported(ClassMethod $method, Scope $scope, ClassReflection $class, string $deprecation, bool $isClassDeprecation): bool;

    /**
     * @return list<IdentifierRuleError>
     */
    public function check(ClassMethod $method, Scope $scope, ClassReflection $class, string $deprecation, bool $isClassDeprecation, \Closure $methodContent): array;
}
