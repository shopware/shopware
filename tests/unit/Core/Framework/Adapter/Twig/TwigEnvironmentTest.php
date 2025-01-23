<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Twig\Loader\ArrayLoader;
use Twig\Source;

/**
 * @internal
 */
#[CoversClass(TwigEnvironment::class)]
class TwigEnvironmentTest extends TestCase
{
    public function testUsesShopwareFunctions(): void
    {
        $twig = new TwigEnvironment(new ArrayLoader(['bla' => '{{ test.bla }}']));

        $code = $twig->compileSource(new Source('{{ test.bla }}', 'bla'));

        static::assertStringContainsString('use Shopware\Core\Framework\Adapter\Twig\SwTwigFunction;', $code);
        static::assertStringContainsString('SwTwigFunction::getAttribute', $code);
    }
}
