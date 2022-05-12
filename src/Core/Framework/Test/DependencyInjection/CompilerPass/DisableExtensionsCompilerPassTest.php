<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DependencyInjection\CompilerPass;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\EmptyActiveAppsLoader;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\DisableExtensionsCompilerPass;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class DisableExtensionsCompilerPassTest extends TestCase
{
    use EnvTestBehaviour;

    public function testItOverridesServiceIfEnvVarIsSet(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(ActiveAppsLoader::class, new Definition(ActiveAppsLoader::class));

        $this->setEnvVars(['DISABLE_EXTENSIONS' => 1]);

        $pass = new DisableExtensionsCompilerPass();
        $pass->process($container);

        static::assertEquals(EmptyActiveAppsLoader::class, $container->getDefinition(ActiveAppsLoader::class)->getClass());
    }

    public function testItDoesNotOverridesServiceIfEnvVarIsNotSet(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(ActiveAppsLoader::class, new Definition(ActiveAppsLoader::class));

        $pass = new DisableExtensionsCompilerPass();
        $pass->process($container);

        static::assertEquals(ActiveAppsLoader::class, $container->getDefinition(ActiveAppsLoader::class)->getClass());
    }
}
