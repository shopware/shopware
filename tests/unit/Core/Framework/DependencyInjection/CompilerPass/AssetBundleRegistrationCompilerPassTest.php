<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\AssetBundleRegistrationCompilerPass;
use Shopware\Core\Framework\Framework;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Asset\Exception\InvalidArgumentException;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[CoversClass(AssetBundleRegistrationCompilerPass::class)]
class AssetBundleRegistrationCompilerPassTest extends TestCase
{
    public function testCompilerPass(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', [
            Framework::class,
            FrameworkBundle::class,
        ]);

        $service = new Definition(Packages::class);
        $service->setPublic(true);
        $container->setDefinition('assets.packages', $service);

        $container->setDefinition('shopware.asset.asset_without_versioning', new Definition(Package::class));
        $container->setDefinition('shopware.asset.asset.version_strategy', new Definition(EmptyVersionStrategy::class));

        $compilerPass = new AssetBundleRegistrationCompilerPass();

        $container->addCompilerPass($compilerPass);
        $compilerPass->process($container);

        $container->set('shopware.asset.asset_without_versioning', $this->createMock(Package::class));

        /** @var Packages $assetService */
        $assetService = $container->get('assets.packages');

        $assetService->getPackage('@Framework');

        static::expectException(InvalidArgumentException::class);
        $assetService->getPackage('@FrameworkBundle');
    }
}
