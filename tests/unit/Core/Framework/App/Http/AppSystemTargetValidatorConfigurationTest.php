<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Validation\WebhookTargetValidator;
use Symfony\Bundle\MonologBundle\DependencyInjection\MonologExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
class AppSystemTargetValidatorConfigurationTest extends TestCase
{
    public function testPassesNullForTheOptionalDnsResolver(): void
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../../../../../src/Core/Framework/DependencyInjection'));
        $loader->load('app.xml');

        $dnsResolver = $container->getDefinition('shopware.app_system.target_validator')->getArgument(2);

        static::assertInstanceOf(Reference::class, $dnsResolver);
        static::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $dnsResolver->getInvalidBehavior());
    }

    public function testConfiguresTheAppSystemTestValidatorWithItsClass(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new MonologExtension());
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../../../../../src/Core/Framework/DependencyInjection'));
        $loader->load('services_test.xml');

        static::assertSame(WebhookTargetValidator::class, $container->getDefinition('shopware.app_system.target_validator')->getClass());
    }
}
