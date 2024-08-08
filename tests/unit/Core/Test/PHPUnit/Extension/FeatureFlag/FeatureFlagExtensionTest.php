<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit\Extension\FeatureFlag;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\PHPUnit\Extension\FeatureFlag\FeatureFlagExtension;

/**
 * @internal
 */
#[CoversClass(FeatureFlagExtension::class)]
class FeatureFlagExtensionTest extends TestCase
{
    public function testValidNamespacesWillBeAddedToTestNamespaces(): void
    {
        $defaultNamespace = 'Shopware\\Tests\\Unit\\';
        $namespaceOne = 'New\\Namespace\\One\\';
        $namespaceTwo = 'New\\Namespace\\Two\\';

        FeatureFlagExtension::addTestNamespace($namespaceOne);
        FeatureFlagExtension::addTestNamespace($namespaceTwo);

        static::assertCount(3, FeatureFlagExtension::getTestNamespaces());
        static::assertCount(1, array_filter(FeatureFlagExtension::getTestNamespaces(), static fn (string $n) => $n === $defaultNamespace));
        static::assertCount(1, array_filter(FeatureFlagExtension::getTestNamespaces(), static fn (string $n) => $n === $namespaceOne));
        static::assertCount(1, array_filter(FeatureFlagExtension::getTestNamespaces(), static fn (string $n) => $n === $namespaceTwo));
    }

    /**
     * @psalm-param class-string<\Throwable> $exceptionClass
     */
    #[DataProvider('invalidNamespaceDataProvider')]
    public function testAddingInvalidNamespaceWillThrowException(
        string $namespace,
        string $exceptionClass,
        string $exceptionMessage
    ): void {
        $this->expectException($exceptionClass);
        $this->expectExceptionMessage($exceptionMessage);

        FeatureFlagExtension::addTestNamespace($namespace);

        static::assertNotContains($namespace, FeatureFlagExtension::getTestNamespaces());
    }

    /**
     * @return iterable<array{string, string, string}>
     */
    public static function invalidNamespaceDataProvider(): iterable
    {
        yield 'empty string namespace' => [
            '',
            \InvalidArgumentException::class,
            'Namespace must be a valid PHP namespace ending with a backslash like this "Shopware\Tests\Unit\", "" given.',
        ];

        yield 'white space string namespace' => [
            ' ',
            \InvalidArgumentException::class,
            'Namespace must be a valid PHP namespace ending with a backslash like this "Shopware\Tests\Unit\", " " given.',
        ];

        yield 'white spaces string namespace' => [
            '  ',
            \InvalidArgumentException::class,
            'Namespace must be a valid PHP namespace ending with a backslash like this "Shopware\Tests\Unit\", "  " given.',
        ];

        yield 'white spaces string namespace with characters' => [
            ' a ',
            \InvalidArgumentException::class,
            'Namespace must be a valid PHP namespace ending with a backslash like this "Shopware\Tests\Unit\", " a " given.',
        ];

        yield 'white spaces string namespace and backslashes' => [
            '  a\\b  ',
            \InvalidArgumentException::class,
            'Namespace must be a valid PHP namespace ending with a backslash like this "Shopware\Tests\Unit\", "  a\b  " given.',
        ];

        yield 'namespace with only backslashes' => [
            '\\',
            \InvalidArgumentException::class,
            'Namespace must be a valid PHP namespace ending with a backslash like this "Shopware\Tests\Unit\", "\" given.',
        ];

        yield 'namespace with backslashes at the beginning' => [
            '\\a\\b\\',
            \InvalidArgumentException::class,
            'Namespace must be a valid PHP namespace ending with a backslash like this "Shopware\Tests\Unit\", "\a\b\" given.',
        ];

        yield 'namespace without backslashes at the end' => [
            'valid\namespace\without\backslash\at\the\end',
            \InvalidArgumentException::class,
            'Namespace must be a valid PHP namespace ending with a backslash like this "Shopware\Tests\Unit\", "valid\namespace\without\backslash\at\the\end" given.',
        ];

        yield 'namespace already present' => [
            'Shopware\\Tests\\Unit\\',
            \InvalidArgumentException::class,
            'Namespace "Shopware\Tests\Unit\" was already added to test namespaces.',
        ];
    }
}
