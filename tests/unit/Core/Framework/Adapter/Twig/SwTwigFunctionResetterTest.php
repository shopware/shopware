<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\SwTwigFunction;
use Shopware\Core\Framework\Adapter\Twig\SwTwigFunctionResetter;
use Twig\Environment;
use Twig\Runtime\EscaperRuntime;

/**
 * @internal
 */
#[CoversClass(SwTwigFunctionResetter::class)]
class SwTwigFunctionResetterTest extends TestCase
{
    private MockObject&Environment $environmentMock;

    protected function setUp(): void
    {
        $this->environmentMock = $this->createMock(Environment::class);
    }

    protected function tearDown(): void
    {
        // Clean up static cache after each test
        SwTwigFunction::resetEscapeCache();
    }

    public function testResetCallsSwTwigFunctionResetEscapeCache(): void
    {
        $env = $this->environmentMock;
        $runtimeCallCount = 0;

        $escaperRuntime = new EscaperRuntime($env);
        $env->method('getRuntime')->willReturnCallback(static function () use ($escaperRuntime, &$runtimeCallCount) {
            ++$runtimeCallCount;

            return $escaperRuntime;
        });

        // Populate the cache
        SwTwigFunction::escapeFilter($env, 'resetter_test_string', 'html', 'UTF-8');
        static::assertSame(1, $runtimeCallCount);

        // Verify cache is used
        SwTwigFunction::escapeFilter($env, 'resetter_test_string', 'html', 'UTF-8');
        // @phpstan-ignore staticMethod.alreadyNarrowedType (PHPStan doesn't track reference through callback)
        static::assertSame(1, $runtimeCallCount, 'Cache should be used');

        // Call resetter
        $resetter = new SwTwigFunctionResetter();
        $resetter->reset();

        // After reset, cache should be cleared
        SwTwigFunction::escapeFilter($env, 'resetter_test_string', 'html', 'UTF-8');
        // @phpstan-ignore staticMethod.impossibleType (PHPStan doesn't track reference through callback)
        static::assertSame(2, $runtimeCallCount, 'After resetter->reset(), cache should be cleared');
    }
}
