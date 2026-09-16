<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation;

use PHPStan\BetterReflection\Reflection\ReflectionMethod as BetterReflectionMethod;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedMethodReflection;
use Shopware\Core\Framework\Deprecation\BCChange\BCChangeAttribute;
use Shopware\Core\Framework\Log\Package;

/**
 * Detects announced BC changes on reflected symbols inside PHPStan rules.
 *
 * @internal
 */
#[Package('framework')]
final class BCChangeMarkers
{
    /**
     * @param class-string<BCChangeAttribute> $attributeClass
     */
    public static function has(
        string $attributeClass,
        ClassReflection|ExtendedMethodReflection|BetterReflectionMethod|\ReflectionMethod $subject,
    ): bool {
        foreach ($subject->getAttributes() as $attribute) {
            if ($attribute->getName() === $attributeClass) {
                return true;
            }
        }

        return false;
    }
}
