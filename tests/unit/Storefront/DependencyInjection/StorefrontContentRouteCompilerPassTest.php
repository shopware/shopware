<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\ContentRouteCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass\GeneratedContentRouteAssertion;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentRouteCompilerPass::class)]
class StorefrontContentRouteCompilerPassTest extends TestCase
{
    use GeneratedContentRouteAssertion;

    /**
     * The Storefront registers the `header` and `footer` section resolvers in its own DI file;
     * the four output formats come from Core. The eight names below are written out by hand;
     * adding or removing a `content_system.section_resolver` in
     * src/Storefront/DependencyInjection/content-system.php, or an output format in Core, fails
     * this test.
     */
    #[TestDox('generates exactly the eight pinned store-api routes for the header and footer sections')]
    public function testGeneratesThePinnedHeaderAndFooterRoutes(): void
    {
        $projectRoot = \dirname(__DIR__, 4);

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
            $projectRoot . '/src/Core/Framework/DependencyInjection/content-system.php',
            $projectRoot . '/src/Storefront/DependencyInjection/content-system.php',
        );
    }
}
