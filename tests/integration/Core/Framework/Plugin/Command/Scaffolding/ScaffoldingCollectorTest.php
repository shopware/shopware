<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Plugin\Command\Scaffolding;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\CommandGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\EventSubscriberGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\StoreApiRouteGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\StorefrontControllerGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingCollector;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class ScaffoldingCollectorTest extends TestCase
{
    private Filesystem $filesystem;

    private string $directory;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/' . uniqid('scaffolding-collector-', true);
        $this->filesystem->mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testGeneratedServicesPhpIsValidLoadableConfig(): void
    {
        $configuration = new PluginScaffoldConfiguration(
            'SwagExample',
            'Swag\Example',
            'custom/plugins/SwagExample',
            [
                StorefrontControllerGenerator::OPTION_NAME => true,
                EventSubscriberGenerator::OPTION_NAME => true,
                CommandGenerator::OPTION_NAME => true,
                StoreApiRouteGenerator::OPTION_NAME => true,
                PluginScaffoldConfiguration::ROUTE_XML_OPTION_NAME => true,
            ],
        );

        $collector = new ScaffoldingCollector([
            new StorefrontControllerGenerator(),
            new EventSubscriberGenerator(),
            new CommandGenerator(),
            new StoreApiRouteGenerator(),
        ]);

        $content = $collector->collect($configuration)->get('src/Resources/config/services.php')?->getContent();
        static::assertIsString($content);

        $this->filesystem->dumpFile($this->directory . '/services.php', $content);

        // Loading through the real PhpFileLoader proves the generated file is valid,
        // executable DI config — not just string-shaped. `::class` ids resolve at
        // compile time, so the (non-existent) plugin classes need not be autoloadable.
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator($this->directory)))->load('services.php');

        $controller = 'Swag\Example\Storefront\Controller\ExampleController';
        static::assertTrue($container->hasDefinition($controller));
        static::assertTrue($container->getDefinition($controller)->isPublic());
        static::assertTrue($container->hasDefinition('Swag\Example\Subscriber\MySubscriber'));
        static::assertArrayHasKey('kernel.event_subscriber', $container->getDefinition('Swag\Example\Subscriber\MySubscriber')->getTags());
        static::assertTrue($container->hasDefinition('Swag\Example\Command\ExampleCommand'));

        $route = 'Swag\Example\Core\Content\Example\SalesChannel\ExampleRoute';
        static::assertTrue($container->hasDefinition($route));
        // Symfony's ControllerResolver fetches route services from the container
        // at request time, so they must be public.
        static::assertTrue($container->getDefinition($route)->isPublic());
        static::assertEquals([new Reference('product.repository')], $container->getDefinition($route)->getArguments());
    }
}
