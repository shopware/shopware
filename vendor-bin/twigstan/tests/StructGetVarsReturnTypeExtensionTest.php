<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PHPStan\Analyser\DependencyTracker;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Testing\TypeInferenceTestCase;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\ObjectType;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StructGetVarsReturnTypeExtension::class)]
class StructGetVarsReturnTypeExtensionTest extends TypeInferenceTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/fixtures/struct-get-vars.neon'];
    }

    public function testInfersTypesThroughActualCallsAndArrayMerges(): void
    {
        $assertions = self::gatherAssertTypes(__DIR__ . '/fixtures/struct-get-vars.php');
        static::assertNotEmpty($assertions);
        foreach ($assertions as $assertion) {
            $this->assertFileAsserts(...$assertion);
        }
    }

    public function testRespectsRuntimeVisibilityAndOptionalInitialization(): void
    {
        $class = self::getContainer()->getByType(ReflectionProvider::class)->getClass(StructVarsFixture::class);
        $scope = static::createStubForIntersectionOfInterfaces([Scope::class, DependencyTracker::class]);
        $scope->method('getType')->willReturn(new ObjectType(StructVarsFixture::class));
        $extension = new StructGetVarsReturnTypeExtension();
        $type = $extension->getTypeFromMethodCall($class->getNativeMethod('getVars'), new MethodCall(new Variable('struct'), 'getVars'), $scope);
        static::assertNotNull($type);
        $arrays = $type->getConstantArrays();
        static::assertCount(1, $arrays);
        $fields = array_map(static fn (ConstantIntegerType|ConstantStringType $key): int|string => $key->getValue(), $arrays[0]->getKeyTypes());
        $runtime = (new StructVarsFixture())->getVars();

        static::assertFalse($runtime['enabled']);
        static::assertNull($runtime['inherited']);
        static::assertArrayNotHasKey('hidden', $runtime);
        static::assertArrayNotHasKey('pending', $runtime);
        static::assertArrayNotHasKey('shared', $runtime);
        static::assertSame('virtual', $runtime['virtual']);
        static::assertContains('inherited', $fields);
        static::assertNotContains('hidden', $fields);
        static::assertNotContains('shared', $fields);
        static::assertContains('virtual', $fields);
        static::assertTrue($type->hasOffsetValueType(new ConstantStringType('enabled'))->yes());
        static::assertTrue($type->hasOffsetValueType(new ConstantStringType('pending'))->maybe());
        static::assertTrue($type->hasOffsetValueType(new ConstantStringType('dynamic'))->maybe());
    }

    public function testDoesNotReplaceOverriddenImplementations(): void
    {
        $provider = self::getContainer()->getByType(ReflectionProvider::class);
        $extension = new StructGetVarsReturnTypeExtension();
        static::assertFalse($extension->isMethodSupported($provider->getClass(ArrayStruct::class)->getNativeMethod('getVars')));
        static::assertFalse($extension->isMethodSupported($provider->getClass(StructVarsOverride::class)->getNativeMethod('getVars')));
    }

    public function testFallsBackForReceiverIntersectionsWithoutAnExportableShape(): void
    {
        $class = self::getContainer()->getByType(ReflectionProvider::class)->getClass(StructVarsFixture::class);
        $scope = static::createStubForIntersectionOfInterfaces([Scope::class, DependencyTracker::class]);
        $scope->method('getType')->willReturn(new IntersectionType([new ObjectType(StructVarsFixture::class), new ObjectType(\Countable::class)]));

        static::assertNull((new StructGetVarsReturnTypeExtension())->getTypeFromMethodCall($class->getNativeMethod('getVars'), new MethodCall(new Variable('struct'), 'getVars'), $scope));
    }
}

/**
 * @internal
 */
#[Package('framework')]
class StructVarsParent extends Struct
{
    protected ?string $inherited = null;
}

/**
 * @internal
 */
#[Package('framework')]
class StructVarsFixture extends StructVarsParent
{
    public static string $shared = 'shared';

    public string $pending;

    public string $virtual {
        get => 'virtual';
    }

    protected bool $enabled = false;

    /**
     * @var array<string, int>
     */
    protected array $values = [];

    // @phpstan-ignore property.onlyWritten (Fixture for properties excluded by getVars.)
    private string $hidden = 'hidden';
}

/**
 * @internal
 */
#[Package('framework')]
class StructVarsOverride extends Struct
{
    /**
     * @return array{custom: int}
     */
    public function getVars(): array
    {
        return ['custom' => 42];
    }
}
