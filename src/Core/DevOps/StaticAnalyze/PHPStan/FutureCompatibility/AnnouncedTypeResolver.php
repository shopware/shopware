<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\FutureCompatibility;

use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Shopware\Core\Framework\Log\Package;

/**
 * Resolves the type strings carried by BC-change attributes (`newType`, `parameterType`)
 * to PHPStan types.
 *
 * BCChangeAttributeUsageRule guarantees payloads resolve as written (classes referenced
 * via ::class). Third-party attributes without that guarantee yield null for
 * unresolvable strings and the caller must skip the check - no false positives.
 *
 * @internal
 */
#[Package('framework')]
class AnnouncedTypeResolver
{
    public function __construct(
        private readonly TypeStringResolver $typeStringResolver,
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    /**
     * @param class-string|string $declaringClass
     */
    public function resolve(string $typeString, string $declaringClass): ?Type
    {
        if (\in_array(\strtolower($typeString), ['self', 'static', '$this'], true)) {
            return new ObjectType($declaringClass);
        }

        try {
            $type = $this->typeStringResolver->resolve($typeString);
        } catch (\Throwable) {
            return null;
        }

        foreach ($type->getReferencedClasses() as $referenced) {
            if (!$this->reflectionProvider->hasClass($referenced)) {
                return null;
            }
        }

        return $type;
    }
}
