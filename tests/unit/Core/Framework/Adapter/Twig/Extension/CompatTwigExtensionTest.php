<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Twig\Extension\CompatTwigExtension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CompatTwigExtension::class)]
class CompatTwigExtensionTest extends TestCase
{
    #[DisabledFeatures(['v6.9.0.0'])]
    public function testRegistersOnlyFunctionsForActiveFeatures(): void
    {
        $extension = new CompatTwigExtension([
            'v6.8.0.0' => ['sw_breadcrumb_full'],
            'v6.9.0.0' => ['future_function'],
        ]);

        static::assertSame(['sw_breadcrumb_full'], array_map(static fn ($function) => $function->getName(), $extension->getFunctions()));
    }

    #[DisabledFeatures(['v6.8.0.0', 'v6.9.0.0'])]
    public function testRegistersNoFunctionsForInactiveFeatures(): void
    {
        $extension = new CompatTwigExtension(['v6.8.0.0' => ['sw_breadcrumb_full']]);

        static::assertSame([], $extension->getFunctions());
    }

    public function testTwigCanParseAnInactiveCallToARemovedFunction(): void
    {
        $twig = new Environment(new ArrayLoader([
            'template' => '{% if false %}{{ sw_breadcrumb_full(null, null) }}{% endif %}ok',
        ]));
        $twig->addExtension(new CompatTwigExtension(['v6.8.0.0' => ['sw_breadcrumb_full']]));

        static::assertSame('ok', $twig->render('template'));
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the compatibility function registration
     */
    public function testRemovedFunctionThrowsWhenCalled(): void
    {
        $extension = new CompatTwigExtension(['v6.8.0.0' => ['sw_breadcrumb_full']]);
        $function = $extension->getFunctions()[0];
        $callback = $function->getCallable();
        static::assertIsCallable($callback);

        static::expectExceptionObject(AdapterException::invalidArgument('Twig function "sw_breadcrumb_full" was removed with feature "v6.8.0.0".'));

        $callback();
    }
}
