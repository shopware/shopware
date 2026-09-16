<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Deprecation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Deprecation\BCChange\ClassMoved;
use Shopware\Core\Framework\Deprecation\ClassAliasRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ClassAliasRegistry::class)]
class ClassAliasRegistryTest extends TestCase
{
    /**
     * @return iterable<string, array{previous: non-empty-string, current: class-string}>
     */
    public static function classAliasProvider(): iterable
    {
        foreach (ClassAliasRegistry::ALIASES as $previous => $current) {
            yield $previous => ['previous' => $previous, 'current' => $current];
        }
    }

    /**
     * @param class-string $current
     */
    #[DataProvider('classAliasProvider')]
    public function testClassAliasIsRegisteredEagerly(string $previous, string $current): void
    {
        static::assertTrue(class_exists($previous, autoload: false));
        static::assertTrue(class_exists($current, autoload: false));
        static::assertTrue(is_a($previous, $current, allow_string: true));
        static::assertTrue(is_a($current, $previous, allow_string: true));
    }

    /**
     * @param class-string $current
     */
    #[DataProvider('classAliasProvider')]
    public function testEveryRegisteredClassAliasIsDeclaredByAttribute(string $previous, string $current): void
    {
        $attributes = (new \ReflectionClass($current))->getAttributes(ClassMoved::class);
        $previousClassNames = array_map(
            static fn (\ReflectionAttribute $attribute): string => $attribute->newInstance()->previousClassName,
            $attributes,
        );

        static::assertContains(
            $previous,
            $previousClassNames,
            \sprintf('Add #[ClassMoved(previousClassName: \'%s\')] to %s.', $previous, $current),
        );
    }
}
