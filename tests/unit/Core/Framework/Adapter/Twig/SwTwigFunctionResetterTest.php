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

    public function testEscapeFilterCallsGetRuntimeAfterReset(): void
    {
        $env = $this->environmentMock;

        $env->expects($this->exactly(2))
            ->method('getRuntime')
            ->willReturn(new EscaperRuntime($env));

        // First call to populate the cache
        SwTwigFunction::escapeFilter($env, 'resetter_test_string', 'html', 'UTF-8');

        // Reset the cache
        $resetter = new SwTwigFunctionResetter();
        $resetter->reset();

        // After reset, getRuntime should be called again
        SwTwigFunction::escapeFilter($env, 'resetter_test_string', 'html', 'UTF-8');
    }
}
