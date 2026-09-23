<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Deprecation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\ClassAliasMap;
use Shopware\Core\Framework\Deprecation\ClassAliasRegistry;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ClassAliasRegistry::class)]
class ClassAliasRegistryTest extends TestCase
{
    public function testAllRegisteredAliasesAreLoaded(): void
    {
        require \dirname(__DIR__, 5) . '/src/Core/Framework/Deprecation/class_aliases.php';

        foreach (ClassAliasRegistry::ALIASES as $previousClassName => $currentClassName) {
            // @phpstan-ignore function.impossibleType (Aliases are registered by the Composer bootstrap.)
            static::assertTrue(class_exists($previousClassName, autoload: false));
            // @phpstan-ignore argument.unresolvableType, method.unresolvableReturnType (Aliases are registered by the Composer bootstrap.)
            static::assertTrue($currentClassName === (new \ReflectionClass($previousClassName))->getName());
        }
    }

    public function testPackageAliasesAreRegisteredAndExposedToTooling(): void
    {
        $previousClassName = 'Shopware\\Tests\\Unit\\Core\\Framework\\Deprecation\\LegacyPackageAlias';

        ClassAliasRegistry::registerAliases([
            $previousClassName => self::class,
        ]);

        static::assertTrue(class_exists($previousClassName, autoload: false));
        // @phpstan-ignore argument.type (The alias is registered immediately above.)
        static::assertSame(self::class, (new \ReflectionClass($previousClassName))->getName());
        static::assertSame(self::class, ClassAliasRegistry::aliases()[$previousClassName]);
        static::assertSame(self::class, (new ClassAliasMap())->canonicalClassName($previousClassName));
    }

    public function testAliasRegistrationFailsForConflictingClass(): void
    {
        $projectRoot = \dirname(__DIR__, 5);
        $script = <<<'PHP'
namespace Shopware\Administration\Controller {
    class NotificationController {}
}

namespace {
    try {
        require $argv[1];
        require $argv[2];
    } catch (\LogicException $exception) {
        fwrite(STDERR, $exception->getMessage());

        throw $exception;
    }
}
PHP;

        $process = new Process([
            \PHP_BINARY,
            '-r',
            $script,
            $projectRoot . '/vendor/autoload.php',
            $projectRoot . '/src/Core/Framework/Deprecation/class_aliases.php',
        ]);
        $process->run();

        static::assertFalse($process->isSuccessful());
        static::assertStringContainsString(
            'Cannot register class alias "Shopware\\Administration\\Controller\\NotificationController" to "Shopware\\Core\\Framework\\Notification\\Api\\NotificationController": the name already refers to "Shopware\\Administration\\Controller\\NotificationController".',
            $process->getErrorOutput(),
        );
    }
}
