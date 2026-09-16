<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Deprecation\BCChange\ClassMoved;
use Shopware\Core\Framework\Deprecation\ClassAliasRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversNothing]
class LegacyClassAliasTest extends TestCase
{
    /**
     * @return iterable<string, array{legacy: string, current: class-string}>
     */
    public static function legacyClassAliasProvider(): iterable
    {
        foreach (ClassAliasRegistry::ALIASES as $legacy => $current) {
            yield $legacy => ['legacy' => $legacy, 'current' => $current];
        }
    }

    #[DataProvider('legacyClassAliasProvider')]
    public function testPreviousNameIsAnAliasAndNotASubclass(string $legacy, string $current): void
    {
        static::assertTrue(is_a($current, $legacy, allow_string: true));
        static::assertTrue(is_a($legacy, $current, allow_string: true));
    }

    #[DataProvider('legacyClassAliasProvider')]
    public function testPreviousNameIsRegisteredEagerly(string $legacy, string $current): void
    {
        static::assertTrue(class_exists($legacy, autoload: false));
        static::assertTrue(class_exists($current, autoload: false));
    }

    /**
     * @param class-string $current
     */
    #[DataProvider('legacyClassAliasProvider')]
    public function testAliasIsDeclaredByClassMovedAttribute(string $legacy, string $current): void
    {
        $attributes = (new \ReflectionClass($current))->getAttributes(ClassMoved::class);
        $previousClassNames = array_map(
            static fn (\ReflectionAttribute $attribute): string => $attribute->newInstance()->previousClassName,
            $attributes,
        );

        static::assertContains($legacy, $previousClassNames);
    }

    #[DataProvider('instantiableLegacyClassAliasProvider')]
    public function testPreviousNameSatisfiesInstanceof(string $legacy, string $current): void
    {
        $instance = new $current();

        // @phpstan-ignore argument.type (the previous name exists only as a runtime alias, so it is not a class-string)
        static::assertInstanceOf($legacy, $instance);
    }

    /**
     * @return iterable<string, array{legacy: string, current: class-string}>
     */
    public static function instantiableLegacyClassAliasProvider(): iterable
    {
        foreach (self::legacyClassAliasProvider() as $name => $case) {
            $reflection = new \ReflectionClass($case['current']);

            if (!$reflection->isInstantiable()) {
                continue;
            }

            if (($reflection->getConstructor()?->getNumberOfRequiredParameters() ?? 0) > 0) {
                continue;
            }

            yield $name => $case;
        }
    }
}
