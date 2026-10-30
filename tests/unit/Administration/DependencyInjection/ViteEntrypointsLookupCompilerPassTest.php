<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\DependencyInjection;

use Pentatrion\ViteBundle\Service\EntrypointsLookup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\DependencyInjection\ViteEntrypointsLookupCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ViteEntrypointsLookupCompilerPass::class)]
class ViteEntrypointsLookupCompilerPassTest extends TestCase
{
    public function testEntrypointsLookupsAreNotShared(): void
    {
        $container = new ContainerBuilder();
        $container->register('pentatrion_vite.entrypoints_lookup[_default]', EntrypointsLookup::class);
        $container->register('other_service', \stdClass::class);

        (new ViteEntrypointsLookupCompilerPass())->process($container);

        static::assertFalse($container->getDefinition('pentatrion_vite.entrypoints_lookup[_default]')->isShared());
        static::assertTrue($container->getDefinition('other_service')->isShared());
    }
}
