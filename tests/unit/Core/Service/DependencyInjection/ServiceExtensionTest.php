<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Service\DependencyInjection\ServiceExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(ServiceExtension::class)]
class ServiceExtensionTest extends TestCase
{
    public function testLoadConfig(): void
    {
        $extension = new ServiceExtension();
        $container = new ContainerBuilder();
        $extension->load([], $container);

        static::assertSame('https://services.shopware.io/services.json', $container->getParameter('shopware.services.registry_url'));
        static::assertFalse($container->getParameter('shopware.services.enabled'));
    }
}
