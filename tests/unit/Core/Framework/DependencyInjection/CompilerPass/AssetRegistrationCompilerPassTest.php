<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\AssetRegistrationCompilerPass;
use Shopware\Storefront\Theme\ThemeCompiler;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[CoversClass(AssetRegistrationCompilerPass::class)]
class AssetRegistrationCompilerPassTest extends TestCase
{
    public function testProcessRegistersAssetTagsAndSetsDefaultPackage(): void
    {
        $container = $this->createContainerWithAssets();

        (new AssetRegistrationCompilerPass())->process($container);

        $assetDefinition = $container->getDefinition('my.asset.service');
        $tags = $assetDefinition->getTags();

        static::assertArrayHasKey('assets.package', $tags);
        static::assertSame('asset', $tags['assets.package'][0]['package']);
    }

    public function testProcessInjectsAssetsIntoThemeCompilerWhenPresent(): void
    {
        $container = $this->createContainerWithAssets();

        $themeCompilerDefinition = new Definition(ThemeCompiler::class);
        // ThemeCompiler expects argument 8 to be the assets array
        for ($i = 0; $i <= 8; ++$i) {
            $themeCompilerDefinition->addArgument(null);
        }
        $themeCompilerDefinition->setPublic(true);
        $container->setDefinition(ThemeCompiler::class, $themeCompilerDefinition);

        (new AssetRegistrationCompilerPass())->process($container);

        $argument = $container->getDefinition(ThemeCompiler::class)->getArgument(8);

        static::assertIsArray($argument);
        static::assertArrayHasKey('asset', $argument);
    }

    public function testProcessDoesNotTouchThemeCompilerWhenAbsent(): void
    {
        $container = $this->createContainerWithAssets();

        static::assertFalse($container->hasDefinition(ThemeCompiler::class));

        // Must not throw
        (new AssetRegistrationCompilerPass())->process($container);
    }

    private function createContainerWithAssets(): ContainerBuilder
    {
        $container = new ContainerBuilder();

        $assetService = new Definition(Packages::class);
        $assetService->setPublic(true);
        $container->setDefinition('assets.packages', $assetService);

        $assetDefinition = new Definition(Package::class);
        $assetDefinition->addTag('shopware.asset', ['asset' => 'asset']);
        $assetDefinition->setPublic(true);
        $container->setDefinition('my.asset.service', $assetDefinition);

        return $container;
    }
}
