<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\CompatTwigExtension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;
use Twig\Source;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CompatTwigExtension::class)]
class CompatTwigExtensionTest extends TestCase
{
    public function testRegistersFunctionsForActiveMajorFlag(): void
    {
        $extension = new CompatTwigExtension();

        static::assertSame([
            'category_url',
            'category_linknewtab',
            'sw_breadcrumb_full',
            'sw_breadcrumb_full_by_id',
        ], array_map(static fn ($function) => $function->getName(), $extension->getFunctions()));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testRegistersNoFunctionsForInactiveMajorFlag(): void
    {
        $extension = new CompatTwigExtension();

        static::assertSame([], $extension->getFunctions());
    }

    public function testTwigCanParseAnInactiveCallToARemovedFunction(): void
    {
        $twig = new Environment(new ArrayLoader([
            'template' => '{% if false %}{{ sw_breadcrumb_full(null, null) }}{% endif %}ok',
        ]));
        $twig->addExtension(new CompatTwigExtension());

        static::assertSame('ok', $twig->render('template'));
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the compatibility function registration
     */
    public function testRemovedFunctionThrowsWhenCalled(): void
    {
        $twig = new Environment(new ArrayLoader([
            'template' => '{{ category_url() }}',
        ]));
        $twig->addExtension(new CompatTwigExtension());

        static::expectExceptionObject(new RuntimeError(
            'An exception has been thrown during the rendering of a template ("Twig function "category_url" was removed with feature "v6.8.0.0".")',
            1,
            new Source('{{ category_url() }}', 'template'),
        ));

        $twig->render('template');
    }
}
