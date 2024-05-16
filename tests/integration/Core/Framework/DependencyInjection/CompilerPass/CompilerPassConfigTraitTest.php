<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\CompilerPassConfigTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ValidateEnvPlaceholdersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;

/**
 * @internal
 *
 * @covers \Shopware\Tests\Integration\Core\Framework\DependencyInjection\CompilerPass\CompilerPassConfigTraitTest
 */
class CompilerPassConfigTraitTest extends TestCase
{
    public function testAutoConfigure(): void
    {
        $parameterBag = new EnvPlaceholderParameterBag();
        $container = new ContainerBuilder($parameterBag);
        $container->setParameter('kernel.debug', true);

        $container->registerExtension(new MockFrameworkExtension());
        $container->prependExtensionConfig('framework', [
            'http_cache' => [
                'default_ttl' => '%env(int:DUMMY_ENV)%',
            ],
        ]);

        $container->addCompilerPass(new ValidateEnvPlaceholdersPass());
        $container->addCompilerPass(new ExampleCompilerPass());

        $this->expectNotToPerformAssertions();
        $container->compile(true);
    }
}

/**
 * @internal
 */
class ExampleCompilerPass implements CompilerPassInterface
{
    use CompilerPassConfigTrait;

    public function process(ContainerBuilder $container): void
    {
        $this->getConfig($container, 'framework');
    }
}

/**
 * @internal
 */
class MockFrameworkExtension implements ExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
    }

    public function getNamespace(): string
    {
        return '';
    }

    public function getXsdValidationBasePath(): string|false
    {
        return false;
    }

    public function getAlias(): string
    {
        return 'framework';
    }
}
