<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\ContentRouteCompilerPass;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentRouteCompilerPass::class)]
class ContentRouteCompilerPassTest extends TestCase
{
    use GeneratedContentRouteAssertion;

    /**
     * Core registers exactly one section resolver, `main`, and all four output formats, so the
     * pass generates four store-api routes. The names below are written out by hand; adding or
     * removing a `content_system.section_resolver` or `content_system.output_format` tag in
     * src/Core/Framework/DependencyInjection/content-system.php fails this test.
     *
     * The Storefront's `header` and `footer` resolvers live in the Storefront's own DI file and
     * are pinned by its own test, so a Core-only installation still passes.
     */
    #[TestDox('generates exactly the four pinned store-api routes for the main section')]
    public function testGeneratesThePinnedMainSectionRoutes(): void
    {
        $this->assertGeneratedContentRouteNames(
            [
                'store-api.content.detail',
                'store-api.content.skeleton',
                'store-api.content.data',
                'store-api.content.decomposed',
            ],
            ['main'],
            \dirname(__DIR__, 6) . '/src/Core/Framework/DependencyInjection/content-system.php',
        );
    }
}
