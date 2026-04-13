<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\SwTwigFunction;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Runtime\EscaperRuntime;
use Twig\Source;

/**
 * @internal
 */
#[CoversClass(SwTwigFunction::class)]
class SwTwigFunctionTest extends TestCase
{
    private MockObject&Environment $environment;

    protected function setUp(): void
    {
        $this->environment = $this->createMock(Environment::class);
        /** This is a fix for a autoload issue in the testsuite. Do not delete. */
        class_exists(CoreExtension::class);
    }

    protected function tearDown(): void
    {
        // Clean up static cache after each test to avoid test pollution
        SwTwigFunction::resetEscapeCache();
    }

    /**
     * @return \Generator<string, array{object: Struct, attribute: string, expected: string|bool|null, arguments?: array}>
     */
    public static function getAttributeDataProvider(): \Generator
    {
        $object = new StructForTests();
        $object->setNoGetter(99);
        $object->setValue('valueValue');
        $object->setVisible(true);

        yield 'null value' => [
            'object' => new ArrayStruct(['test' => null]),
            'attribute' => 'test',
            'expected' => null,
        ];

        yield 'boolean true' => [
            'object' => new ArrayStruct(['test' => true]),
            'attribute' => 'test',
            'expected' => true,
        ];

        yield 'boolean false' => [
            'object' => new ArrayStruct(['test' => false]),
            'attribute' => 'test',
            'expected' => false,
        ];

        yield 'just property' => [
            'object' => new ArrayStruct(['test' => 'value']),
            'attribute' => 'test',
            'expected' => 'value',
        ];

        yield 'getter method' => [
            'object' => $object,
            'attribute' => 'value',
            'expected' => 'valueValue',
        ];

        yield 'isVisible method' => [
            'object' => $object,
            'attribute' => 'isVisible',
            'expected' => true,
        ];

        yield 'method with arguments' => [
            'object' => $object,
            'attribute' => 'getNonExistentProperty',
            'arguments' => ['arg1', 'arg2'],
            'expected' => 'result',
        ];
    }

    /**
     * @param list<string> $arguments
     */
    #[DataProvider('getAttributeDataProvider')]
    public function testGetAttributeWithVariousInputs(Struct $object, string $attribute, string|bool|null $expected, array $arguments = []): void
    {
        $result = SwTwigFunction::getAttribute(
            $this->environment,
            new Source('', 'empty'),
            $object,
            $attribute,
            $arguments
        );

        static::assertSame($expected, $result);
    }

    /**
     * @return \Generator<string, array{input: int|string|null, expected: string}>
     */
    public static function escapeFilterDataProvider(): \Generator
    {
        yield 'null input' => [
            'input' => null,
            'expected' => '',
        ];

        yield 'integer input' => [
            'input' => 123,
            'expected' => '123',
        ];

        yield 'string input' => [
            'input' => 'test',
            'expected' => 'test',
        ];

        yield 'escaped string input' => [
            'input' => '<script>alert("test")</script>',
            'expected' => '&lt;script&gt;alert(&quot;test&quot;)&lt;/script&gt;',
        ];
    }

    #[DataProvider('escapeFilterDataProvider')]
    public function testEscapeFilterWithVariousInputs(int|string|null $input, string $expected): void
    {
        $env = $this->environment;
        $env->method('getRuntime')->willReturn(new EscaperRuntime($env));

        $result = SwTwigFunction::escapeFilter($env, $input, 'html', 'UTF-8');

        static::assertSame($expected, $result);
    }

    public function testEscapeFilterWithCache(): void
    {
        $env = $this->environment;

        // Ensure getRuntime is called only once
        $env->expects($this->once())
            ->method('getRuntime')
            ->willReturn(new EscaperRuntime($env));

        // First call to cache the result
        $string = 'cached_string';
        $result1 = SwTwigFunction::escapeFilter($env, $string, 'html', 'UTF-8');

        // Second call to get the cached result
        $result2 = SwTwigFunction::escapeFilter($env, $string, 'html', 'UTF-8');

        // Assert that the results are the same, indicating the cache was used
        static::assertSame($result1, $result2);
    }

    public function testEscapeFilterDoesNotCacheNonStringInputs(): void
    {
        $env = $this->environment;

        // Expect getRuntime to be called twice, once for each invocation (would be 1 in total with cache)
        $env->expects($this->exactly(2))
            ->method('getRuntime')
            ->willReturn(new EscaperRuntime($env));

        // Use a boolean since $string is mixed, and a non-string input should not be cached
        $result1 = SwTwigFunction::escapeFilter($env, true, 'html', 'UTF-8');
        $result2 = SwTwigFunction::escapeFilter($env, true, 'html', 'UTF-8');

        // Results are the same with same input, but cache was not involved - guaranteed by earlier expectations
        static::assertSame($result1, $result2);
    }

    public function testGetAttributePropagatesThrowable(): void
    {
        $env = $this->createMock(Environment::class);
        $source = new Source('', 'test_template');

        static::expectExceptionObject(new \Exception('Test exception'));

        $struct = new StructForTests();
        $struct->setThrowException(true);

        SwTwigFunction::getAttribute(
            $env,
            $source,
            $struct,
            'nonExistentProperty'
        );
    }
}

/**
 * @internal
 */
class StructForTests extends Struct
{
    private bool $visible;

    private string $value;

    private int $noGetter;

    private bool $throwException = false;

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): void
    {
        $this->visible = $visible;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function setNoGetter(int $noGetter): void
    {
        $this->noGetter = $noGetter;
        if ($this->noGetter > 0) {
            $this->visible = true;
        }
    }

    public function setThrowException(bool $throwException): void
    {
        $this->throwException = $throwException;
    }

    public function getNonExistentProperty(): string
    {
        if ($this->throwException) {
            throw new \Exception('Test exception');
        }

        return 'result';
    }
}
