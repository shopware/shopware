<?php declare(strict_types=1);

namespace Shopware\AreaCountry\DependencyInjection;

use Shopware\Framework\DependencyInjection\TagReplaceTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class ExtensionCompilerPass implements CompilerPassInterface
{
    use TagReplaceTrait;

    public function process(ContainerBuilder $container): void
    {
        $this->replaceArgumentWithTaggedServices($container, 'shopware.areaCountry.factory', 'shopware.areaCountry.extension', 1);

        $services = $container->findTaggedServiceIds('shopware.areaCountry.extension');
        /** @var Definition $service */
        foreach ($services as $service) {
            if ($service->hasTag('kernel.event_subscriber')) {
                continue;
            }
            $service->addTag('kernel.event_subscriber');
        }
    }
}
