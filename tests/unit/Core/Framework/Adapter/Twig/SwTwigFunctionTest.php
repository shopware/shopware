<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\SwTwigFunction;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Runtime\EscaperRuntime;
use Twig\Source;
use Twig\Template;

/**
 * @internal
 */
#[CoversClass(SwTwigFunction::class)]
class SwTwigFunctionTest extends TestCase
{
    private MockObject&Environment $environmentMock;

    protected function setUp(): void
    {
        $this->environmentMock = $this->createMock(Environment::class);
        /** This is a fix for a autoload issue in the testsuite. Do not delete. */
        class_exists(CoreExtension::class);
    }

    protected function tearDown(): void
    {
        // Clean up static cache after each test to avoid test pollution
        SwTwigFunction::resetEscapeCache();
    }

    public function testSwGetAttributeValueNull(): void
    {
        $object = new ArrayStruct(['test' => null]);
        $result = SwTwigFunction::getAttribute($this->environmentMock, new Source('', 'empty'), $object, 'test');

        static::assertNull($result);
    }

    public function testSwGetAttributeValueBool(): void
    {
        $object = new ArrayStruct(['test' => true]);
        $result = SwTwigFunction::getAttribute($this->environmentMock, new Source('', 'empty'), $object, 'test');

        static::assertTrue($result);

        $object = new ArrayStruct(['test' => false]);
        $result = SwTwigFunction::getAttribute($this->environmentMock, new Source('', 'empty'), $object, 'test');

        static::assertFalse($result);
    }

    public function testSwGetAttributeJustProperty(): void
    {
        $object = new ArrayStruct(['test' => 'value']);
        $result = SwTwigFunction::getAttribute($this->environmentMock, new Source('', 'empty'), $object, 'test');

        static::assertSame('value', $result);
    }

    public function testSwGetAttributeGetterMethods(): void
    {
        $object = new StructForTests();
        $object->setNoGetter(99);
        $object->setValue('valueValue');
        $object->setVisible(true);

        $result = SwTwigFunction::getAttribute($this->environmentMock, new Source('', 'empty'), $object, 'noGetter');

        static::assertNull($result);

        $result = SwTwigFunction::getAttribute($this->environmentMock, new Source('', 'empty'), $object, 'value');

        static::assertSame('valueValue', $result);

        $result = SwTwigFunction::getAttribute($this->environmentMock, new Source('', 'empty'), $object, 'getValue');

        static::assertSame('valueValue', $result);

        $result = SwTwigFunction::getAttribute($this->environmentMock, new Source('', 'empty'), $object, 'visible');

        static::assertTrue($result);

        $result = SwTwigFunction::getAttribute($this->environmentMock, new Source('', 'empty'), $object, 'isVisible');

        static::assertTrue($result);

        $result = SwTwigFunction::getAttribute(
            $this->environmentMock,
            new Source('', 'empty'),
            $object,
            'isVisible',
            [],
            Template::METHOD_CALL
        );

        static::assertTrue($result);
    }

    public function testEscapeFilterWithNullInput(): void
    {
        $env = $this->environmentMock;
        $env->method('getRuntime')->willReturn(new EscaperRuntime($env));
        $result = SwTwigFunction::escapeFilter($env, null, 'html', 'UTF-8');

        static::assertSame('', $result);
    }

    public function testEscapeFilterWithIntegerInput(): void
    {
        $env = $this->environmentMock;
        $env->method('getRuntime')->willReturn(new EscaperRuntime($env));
        $result = SwTwigFunction::escapeFilter($env, 123, 'html', 'UTF-8');

        static::assertSame('123', $result);
    }

    public function testEscapeFilterWithStringInput(): void
    {
        $env = $this->environmentMock;
        $env->method('getRuntime')->willReturn(new EscaperRuntime($env));
        $result = SwTwigFunction::escapeFilter($env, 'test', 'html', 'UTF-8');

        static::assertSame('test', $result);
    }

    public function testEscapeFilterReallyEscapeString(): void
    {
        $env = $this->environmentMock;
        $env->method('getRuntime')->willReturn(new EscaperRuntime($env));
        $result = SwTwigFunction::escapeFilter($env, '<script>alert("test")</script>', 'html', 'UTF-8');

        static::assertSame('&lt;script&gt;alert(&quot;test&quot;)&lt;/script&gt;', $result);
    }

    public function testEscapeFilterWithCachedStringInput(): void
    {
        $env = $this->environmentMock;
        $env->method('getRuntime')->willReturn(new EscaperRuntime($env));

        // First call to cache the result
        SwTwigFunction::escapeFilter($env, 'cached_string', 'html', 'UTF-8');

        // Second call to get the cached result
        $result = SwTwigFunction::escapeFilter($env, 'cached_string', 'html', 'UTF-8');

        static::assertSame('cached_string', $result);
    }

    public function testResetEscapeCacheClearsCache(): void
    {
        $env = $this->environmentMock;
        $runtimeCallCount = 0;

        $escaperRuntime = new EscaperRuntime($env);
        $env->method('getRuntime')->willReturnCallback(function () use ($escaperRuntime, &$runtimeCallCount) {
            ++$runtimeCallCount;

            return $escaperRuntime;
        });

        // First call - should call getRuntime
        SwTwigFunction::escapeFilter($env, 'test_reset_string', 'html', 'UTF-8');
        static::assertSame(1, $runtimeCallCount);

        // Second call - should use cache, NOT call getRuntime
        SwTwigFunction::escapeFilter($env, 'test_reset_string', 'html', 'UTF-8');
        // @phpstan-ignore staticMethod.alreadyNarrowedType (PHPStan doesn't track reference through callback)
        static::assertSame(1, $runtimeCallCount, 'Second call should use cache');

        // Reset the cache
        SwTwigFunction::resetEscapeCache();

        // Third call after reset - should call getRuntime again
        SwTwigFunction::escapeFilter($env, 'test_reset_string', 'html', 'UTF-8');
        // @phpstan-ignore staticMethod.impossibleType (PHPStan doesn't track reference through callback)
        static::assertSame(2, $runtimeCallCount, 'After reset, cache should be empty and getRuntime called again');
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
}
