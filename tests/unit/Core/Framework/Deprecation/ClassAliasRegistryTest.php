<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Deprecation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Deprecation\ClassAliasRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ClassAliasRegistry::class)]
class ClassAliasRegistryTest extends TestCase
{
    public function testAllRegisteredAliasesAreLoaded(): void
    {
        require_once \dirname(__DIR__, 5) . '/src/Core/Framework/Deprecation/class_aliases.php';

        foreach (ClassAliasRegistry::ALIASES as $previousClassName => $currentClassName) {
            // @phpstan-ignore function.impossibleType (The test verifies the aliases registered dynamically above.)
            static::assertTrue(class_exists($previousClassName, autoload: false));
            // @phpstan-ignore argument.unresolvableType, method.unresolvableReturnType (PHPStan cannot resolve dynamic aliases.)
            static::assertTrue($currentClassName === (new \ReflectionClass($previousClassName))->getName());
        }
    }
}
