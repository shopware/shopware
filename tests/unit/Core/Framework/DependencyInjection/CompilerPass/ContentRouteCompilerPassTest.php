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
     * removing a `content_system.section_resolver` tag for the `main` section, or a
     * `content_system.output_format` tag, in
     * src/Core/Framework/DependencyInjection/content-system.php fails this test.
     *
     * Resolvers for other sections are registered by the bundle that ships them, so this test
     * pins the routes of the `main` section only.
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

    /**
     * A section other than `main` gets a path-less route named after its route path segment. The
     * `header` and `footer` resolvers come from a test DI file; the four output formats come from
     * Core. The eight names and the eight paths below are written out by hand.
     */
    #[TestDox('generates exactly the eight pinned path-less store-api routes for the header and footer sections')]
    public function testGeneratesThePinnedPathLessSectionRoutes(): void
    {
        $coreServices = \dirname(__DIR__, 6) . '/src/Core/Framework/DependencyInjection/content-system.php';
        $sectionResolvers = __DIR__ . '/fixtures/content-route/section-resolvers.php';

        $this->assertGeneratedContentRouteNames(
            [
                'store-api.content-header',
                'store-api.content-header.skeleton',
                'store-api.content-header.data',
                'store-api.content-header.decomposed',
                'store-api.content-footer',
                'store-api.content-footer.skeleton',
                'store-api.content-footer.data',
                'store-api.content-footer.decomposed',
            ],
            ['header', 'footer'],
            $coreServices,
            $sectionResolvers,
        );

        $this->assertGeneratedPathLessSectionRoutePaths(
            [
                'store-api.content-header' => '/store-api/content-header',
                'store-api.content-header.skeleton' => '/store-api/content-header-skeleton',
                'store-api.content-header.data' => '/store-api/content-header-data',
                'store-api.content-header.decomposed' => '/store-api/content-header-decomposed',
                'store-api.content-footer' => '/store-api/content-footer',
                'store-api.content-footer.skeleton' => '/store-api/content-footer-skeleton',
                'store-api.content-footer.data' => '/store-api/content-footer-data',
                'store-api.content-footer.decomposed' => '/store-api/content-footer-decomposed',
            ],
            ['header', 'footer'],
            $coreServices,
            $sectionResolvers,
        );
    }
}
