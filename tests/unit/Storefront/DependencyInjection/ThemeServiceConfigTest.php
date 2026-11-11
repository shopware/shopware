<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * Guards the service definitions of the Storefront theme.php.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversNothing]
class ThemeServiceConfigTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $loader = new PhpFileLoader($this->container, new FileLocator());
        $loader->load(__DIR__ . '/../../../../src/Storefront/DependencyInjection/theme.php');
    }

    public function testSalesChannelThemeLoaderIsResetBetweenRequests(): void
    {
        static::assertSame(
            [['method' => 'reset']],
            $this->container->getDefinition(DatabaseSalesChannelThemeLoader::class)->getTag('kernel.reset'),
            'DatabaseSalesChannelThemeLoader memoises the themes per sales channel, so it must be reset between requests and worker messages',
        );
    }

    public function testThemeServiceIsResetBetweenRequests(): void
    {
        static::assertSame(
            [['method' => 'reset']],
            $this->container->getDefinition(ThemeService::class)->getTag('kernel.reset'),
            'ThemeService notifies the start of an async compilation once per request, so it must be reset between requests',
        );
    }
}
