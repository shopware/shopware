<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Deprecation\BCChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Deprecation\BCChange\BCChangeAttribute;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesAbstract;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesFinal;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Deprecation\BCChange\CallSiteCompatibilityChange;
use Shopware\Core\Framework\Deprecation\BCChange\ClassHierarchyChange;
use Shopware\Core\Framework\Deprecation\BCChange\ExceptionChange;
use Shopware\Core\Framework\Deprecation\BCChange\ExtenderCompatibilityChange;
use Shopware\Core\Framework\Deprecation\BCChange\NewOptionalParameter;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterNameChange;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeChange;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeExtension;
use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeChange;
use Shopware\Core\Framework\Deprecation\BCChange\VisibilityChange;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(BecomesAbstract::class)]
#[CoversClass(BecomesFinal::class)]
#[CoversClass(BecomesInternal::class)]
#[CoversClass(ClassHierarchyChange::class)]
#[CoversClass(ExceptionChange::class)]
#[CoversClass(NewOptionalParameter::class)]
#[CoversClass(ParameterNameChange::class)]
#[CoversClass(ParameterTypeChange::class)]
#[CoversClass(ParameterTypeExtension::class)]
#[CoversClass(ReturnTypeChange::class)]
#[CoversClass(VisibilityChange::class)]
class BCChangeAttributesTest extends TestCase
{
    /**
     * @param class-string<BCChangeAttribute> $attributeClass
     * @param list<class-string<BCChangeAttribute>> $expectedAudiences
     */
    #[DataProvider('attributeConfigurationProvider')]
    public function testAttributeConfiguration(string $attributeClass, int $expectedFlags, array $expectedAudiences): void
    {
        $reflection = new \ReflectionClass($attributeClass);

        static::assertTrue($reflection->isFinal(), \sprintf('"%s" must be final', $attributeClass));
        static::assertTrue(
            $reflection->implementsInterface(BCChangeAttribute::class),
            \sprintf('"%s" must implement the marker interface for tooling discovery', $attributeClass)
        );

        $implementedAudiences = array_values(array_filter(
            [CallSiteCompatibilityChange::class, ExtenderCompatibilityChange::class],
            static fn (string $audience) => $reflection->implementsInterface($audience)
        ));
        static::assertSame(
            $expectedAudiences,
            $implementedAudiences,
            \sprintf('"%s" declares unexpected affected audiences', $attributeClass)
        );

        $attributeConfig = $reflection->getAttributes(\Attribute::class);
        static::assertCount(1, $attributeConfig, \sprintf('"%s" must be declared as attribute', $attributeClass));
        static::assertSame(
            $expectedFlags,
            $attributeConfig[0]->newInstance()->flags,
            \sprintf('"%s" declares unexpected attribute targets', $attributeClass)
        );
    }

    /**
     * @return iterable<string, array{class-string<BCChangeAttribute>, int, list<class-string<BCChangeAttribute>>}>
     */
    public static function attributeConfigurationProvider(): iterable
    {
        yield 'return type narrowing targets a single method and affects extenders' => [
            ReturnTypeChange::class,
            \Attribute::TARGET_METHOD,
            [ExtenderCompatibilityChange::class],
        ];

        yield 'new optional parameters target methods, are repeatable per parameter and affect extenders' => [
            NewOptionalParameter::class,
            \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE,
            [ExtenderCompatibilityChange::class],
        ];

        yield 'parameter renames target methods, are repeatable per parameter and affect named-argument call sites' => [
            ParameterNameChange::class,
            \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE,
            [CallSiteCompatibilityChange::class],
        ];

        yield 'parameter type narrowing targets methods, is repeatable per parameter and affects call sites' => [
            ParameterTypeChange::class,
            \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE,
            [CallSiteCompatibilityChange::class],
        ];

        yield 'parameter type widening targets methods, is repeatable per parameter and affects extenders' => [
            ParameterTypeExtension::class,
            \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE,
            [ExtenderCompatibilityChange::class],
        ];

        yield 'exception changes target a single method and affect catching call sites' => [
            ExceptionChange::class,
            \Attribute::TARGET_METHOD,
            [CallSiteCompatibilityChange::class],
        ];

        yield 'becoming internal targets classes and methods and affects call sites and extenders' => [
            BecomesInternal::class,
            \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD,
            [CallSiteCompatibilityChange::class, ExtenderCompatibilityChange::class],
        ];

        yield 'becoming abstract targets a single method and affects extenders' => [
            BecomesAbstract::class,
            \Attribute::TARGET_METHOD,
            [ExtenderCompatibilityChange::class],
        ];

        yield 'becoming final targets classes and affects extenders' => [
            BecomesFinal::class,
            \Attribute::TARGET_CLASS,
            [ExtenderCompatibilityChange::class],
        ];

        yield 'class hierarchy changes target classes and affect call sites and extenders' => [
            ClassHierarchyChange::class,
            \Attribute::TARGET_CLASS,
            [CallSiteCompatibilityChange::class, ExtenderCompatibilityChange::class],
        ];

        yield 'visibility changes target a single method and affect call sites and extenders' => [
            VisibilityChange::class,
            \Attribute::TARGET_METHOD,
            [CallSiteCompatibilityChange::class, ExtenderCompatibilityChange::class],
        ];
    }

    public function testClassLevelAttributesAreDiscoverableViaMarkerInterface(): void
    {
        $reflection = new \ReflectionClass(BCChangeFixture::class);

        $attributes = $reflection->getAttributes(BCChangeAttribute::class, \ReflectionAttribute::IS_INSTANCEOF);
        static::assertCount(3, $attributes);

        $instances = array_map(static fn (\ReflectionAttribute $attribute) => $attribute->newInstance(), $attributes);

        $becomesFinal = self::expectInstanceOf($instances, BecomesFinal::class);
        static::assertSame('v6.8.0', $becomesFinal->version);

        $becomesInternal = self::expectInstanceOf($instances, BecomesInternal::class);
        static::assertSame('v6.8.0', $becomesInternal->version);

        $hierarchyChange = self::expectInstanceOf($instances, ClassHierarchyChange::class);
        static::assertSame('Will no longer extend AbstractExample', $hierarchyChange->description);
    }

    public function testMethodLevelAttributesAreDiscoverableViaMarkerInterface(): void
    {
        $method = new \ReflectionMethod(BCChangeFixture::class, 'methodWithChanges');

        $attributes = $method->getAttributes(BCChangeAttribute::class, \ReflectionAttribute::IS_INSTANCEOF);
        static::assertCount(3, $attributes);

        $instances = array_map(static fn (\ReflectionAttribute $attribute) => $attribute->newInstance(), $attributes);

        $returnTypeChange = self::expectInstanceOf($instances, ReturnTypeChange::class);
        static::assertSame('static', $returnTypeChange->newType);

        $exceptionChange = self::expectInstanceOf($instances, ExceptionChange::class);
        static::assertSame([\RuntimeException::class], $exceptionChange->newExceptions);

        $visibilityChange = self::expectInstanceOf($instances, VisibilityChange::class);
        static::assertSame('protected', $visibilityChange->newVisibility);
    }

    public function testParameterScopedAttributesCanBeRepeatedPerParameter(): void
    {
        $method = new \ReflectionMethod(BCChangeFixture::class, 'methodWithParameterChanges');

        static::assertCount(5, $method->getAttributes(BCChangeAttribute::class, \ReflectionAttribute::IS_INSTANCEOF));

        $newParameters = array_map(
            static fn (\ReflectionAttribute $attribute) => $attribute->newInstance()->parameterName,
            $method->getAttributes(NewOptionalParameter::class)
        );
        static::assertSame(['states', 'behavior'], $newParameters);

        $nameChange = $method->getAttributes(ParameterNameChange::class)[0]->newInstance();
        static::assertSame('old', $nameChange->parameterName);
        static::assertSame('new', $nameChange->newName);

        $typeChange = $method->getAttributes(ParameterTypeChange::class)[0]->newInstance();
        static::assertSame('string', $typeChange->newType);

        $typeExtension = $method->getAttributes(ParameterTypeExtension::class)[0]->newInstance();
        static::assertSame('string|int', $typeExtension->newType);
    }

    /**
     * @template TAttribute of BCChangeAttribute
     *
     * @param list<BCChangeAttribute> $instances
     * @param class-string<TAttribute> $class
     *
     * @return TAttribute
     */
    private static function expectInstanceOf(array $instances, string $class): BCChangeAttribute
    {
        foreach ($instances as $instance) {
            if ($instance instanceof $class) {
                return $instance;
            }
        }

        static::fail(\sprintf('Expected an instance of "%s"', $class));
    }
}

/**
 * @internal test fixture proving the attributes can be applied to their intended targets
 */
#[Package('framework')]
#[BecomesFinal(version: 'v6.8.0')]
#[BecomesInternal(version: 'v6.8.0')]
#[ClassHierarchyChange(version: 'v6.8.0', description: 'Will no longer extend AbstractExample')]
class BCChangeFixture
{
    #[ReturnTypeChange(version: 'v6.8.0', newType: 'static')]
    #[ExceptionChange(version: 'v6.8.0', newExceptions: [\RuntimeException::class])]
    #[VisibilityChange(version: 'v6.8.0', newVisibility: 'protected')]
    public function methodWithChanges(): self
    {
        return $this;
    }

    #[NewOptionalParameter(version: 'v6.8.0', parameterName: 'states', parameterType: 'list<string>')]
    #[NewOptionalParameter(version: 'v6.8.0', parameterName: 'behavior', parameterType: '?string')]
    #[ParameterNameChange(version: 'v6.8.0', parameterName: 'old', newName: 'new')]
    #[ParameterTypeChange(version: 'v6.8.0', parameterName: 'id', newType: 'string')]
    #[ParameterTypeExtension(version: 'v6.8.0', parameterName: 'value', newType: 'string|int')]
    public function methodWithParameterChanges(int|string $id, string $old, string $value): void
    {
    }
}
