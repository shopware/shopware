<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Capability\AbstractUcpCapability;
use Shopware\Core\Framework\Ucp\Capability\CapabilityRegistry;
use Shopware\Core\Framework\Ucp\DependencyInjection\UcpCapabilityCompilerPass;
use Shopware\Core\Framework\Ucp\UcpException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[CoversClass(UcpCapabilityCompilerPass::class)]
class UcpCapabilityCompilerPassTest extends TestCase
{
    public function testCollectsTaggedCapabilities(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(
            CapabilityRegistry::class,
            (new Definition(CapabilityRegistry::class))->setArgument('$capabilities', [])
        );
        $container->setDefinition('cap.cart', (new Definition(FakeCap::class))->addTag('ucp.capability', ['capability' => 'dev.ucp.shopping.cart']));
        $container->setDefinition('cap.checkout', (new Definition(FakeCap::class))->addTag('ucp.capability', ['capability' => 'dev.ucp.shopping.checkout']));

        $pass = new UcpCapabilityCompilerPass();
        $pass->process($container);

        $registry = $container->getDefinition(CapabilityRegistry::class);
        $refs = $registry->getArgument('$capabilities');
        static::assertArrayHasKey('dev.ucp.shopping.cart', $refs);
        static::assertArrayHasKey('dev.ucp.shopping.checkout', $refs);
    }

    public function testRejectsMissingNameAttribute(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(
            CapabilityRegistry::class,
            (new Definition(CapabilityRegistry::class))->setArgument('$capabilities', [])
        );
        $container->setDefinition('cap.bad', (new Definition(FakeCap::class))->addTag('ucp.capability'));

        $this->expectExceptionObject(UcpException::capabilityTagInvalid(
            'cap.bad',
            'missing required "capability" attribute (UCP capability identifier, e.g. "dev.ucp.shopping.cart")'
        ));
        (new UcpCapabilityCompilerPass())->process($container);
    }

    public function testRejectsDuplicateName(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(
            CapabilityRegistry::class,
            (new Definition(CapabilityRegistry::class))->setArgument('$capabilities', [])
        );
        $container->setDefinition('cap.a', (new Definition(FakeCap::class))->addTag('ucp.capability', ['capability' => 'dev.ucp.shopping.cart']));
        $container->setDefinition('cap.b', (new Definition(FakeCap::class))->addTag('ucp.capability', ['capability' => 'dev.ucp.shopping.cart']));

        $this->expectExceptionObject(UcpException::capabilityTagInvalid(
            'cap.b',
            'duplicate capability "dev.ucp.shopping.cart" — already registered by "cap.a"'
        ));
        (new UcpCapabilityCompilerPass())->process($container);
    }
}

/**
 * @internal
 */
class FakeCap extends AbstractUcpCapability
{
    public function getName(): string
    {
        return 'fake';
    }

    public function getSpecUrl(): string
    {
        return 'https://x/spec';
    }

    public function getSchemaUrl(): string
    {
        return 'https://x/schema';
    }
}
