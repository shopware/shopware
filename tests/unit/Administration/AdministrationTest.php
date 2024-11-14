<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration;

use Composer\Autoload\ClassLoader;
use Pentatrion\ViteBundle\PentatrionViteBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Administration;
use Shopware\Administration\DependencyInjection\AdministrationMigrationCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('administration')]
#[CoversClass(Administration::class)]
class AdministrationTest extends TestCase
{
    public function testTemplatePriority(): void
    {
        $administration = new Administration();

        static::assertEquals(-1, $administration->getTemplatePriority());
    }

    public function testBundle(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        // Register the Vite bundle as the Admin bundle tries to loads it's config
        $viteBundle = new PentatrionViteBundle();
        $viteBundle->build($container);

        $viteContainerExtension = $viteBundle->getContainerExtension();
        if ($viteContainerExtension !== null) {
            $container->registerExtension($viteContainerExtension);
        }

        static::assertNotContains(
            AdministrationMigrationCompilerPass::class,
            $this->toClassNames($container->getCompilerPassConfig()->getPasses())
        );

        $administration = new Administration();
        $administration->build($container);

        static::assertContains(
            AdministrationMigrationCompilerPass::class,
            $this->toClassNames($container->getCompilerPassConfig()->getPasses())
        );

        $additionalBundles = $administration->getAdditionalBundles(
            new AdditionalBundleParameters(new ClassLoader(), new KernelPluginCollection(), [])
        );
        static::assertCount(1, $additionalBundles);
        static::assertInstanceOf(PentatrionViteBundle::class, $additionalBundles[0]);
    }

    /**
     * @param CompilerPassInterface[] $initialPasses
     *
     * @return array<int, string>
     */
    protected function toClassNames(array $initialPasses): array
    {
        $result = [];
        foreach ($initialPasses as $pass) {
            $result[] = $pass::class;
        }

        return $result;
    }
}
