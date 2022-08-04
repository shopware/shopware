<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\TestCase;
use Shopware\Core\System\DependencyInjection\CompilerPass\SalesChannelEntityCompilerPass;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Symfony\Component\Config\FileLocator;use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * @internal
 * @covers SalesChannelEntityCompilerPass
 */
class SalesChannelEntityCompilerPassTest extends TestCase
{
    public function testEntityRepositoryAutowiring(): void
    {
        $container = new ContainerBuilder();

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../Resources/config'));
        $loader->load('test_entity_definition_compiled.xml');

        $entityCompilerPass = new SalesChannelEntityCompilerPass();
        $entityCompilerPass->process($container);

        static::assertSame(
            'sales_channel.country.repository',
            (string)$container->getAlias(SalesChannelRepository::class . ' $countryRepository')
        );
        static::assertSame(
            'sales_channel.country_state.repository',
            (string)$container->getAlias(SalesChannelRepository::class . ' $countryStateRepository')
        );
    }
}
