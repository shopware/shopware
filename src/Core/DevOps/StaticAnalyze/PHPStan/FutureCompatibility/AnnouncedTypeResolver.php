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
 * The strings are written as they would appear in the declaring file, so class names may
 * be short. Reflection attributes do not carry the file's use statements; unqualified
 * names are tried as-is and relative to the declaring class's namespace. Unresolvable
 * strings yield null and the caller must skip the check - no false positives.
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

        $namespace = \substr($declaringClass, 0, (int) \strrpos($declaringClass, '\\'));
        foreach ($type->getReferencedClasses() as $referenced) {
            if ($this->reflectionProvider->hasClass($referenced)) {
                continue;
            }

            // try the declaring class's namespace for unqualified names
            $candidate = $namespace . '\\' . $referenced;
            if (\str_contains($referenced, '\\') || !$this->reflectionProvider->hasClass($candidate)) {
                return null;
            }

            $typeString = \preg_replace('/(?<![\w\\\\])' . \preg_quote($referenced, '/') . '(?![\w\\\\])/', '\\' . $candidate, $typeString) ?? $typeString;

            try {
                $type = $this->typeStringResolver->resolve($typeString);
            } catch (\Throwable) {
                return null;
            }
        }

        return $type;
    }
}
