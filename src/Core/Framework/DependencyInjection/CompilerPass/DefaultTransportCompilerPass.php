<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('core')]
class DefaultTransportCompilerPass implements CompilerPassInterface
{
    use CompilerPassConfigTrait;

    public function process(ContainerBuilder $container): void
    {
        // the default transport is defined by the parameter `messenger.default_transport_name`
        $defaultName = $container->getParameter('messenger.default_transport_name');
        if (!\is_string($defaultName)) {
            throw new \TypeError('Parameter `messenger.default_transport_name` should be a string.');
        }
        $id = 'messenger.transport.' . $defaultName;
        $container->addAliases(['messenger.default_transport' => $id]);

        $config = $this->getConfig($container, 'framework');

        if (!\array_key_exists('messenger', $config)) {
            return;
        }

        $mapped = [];
        foreach ($config['messenger']['routing'] as $message => $transports) {
            if (!\array_key_exists('senders', $transports)) {
                continue;
            }
            $mapped[$message] = array_shift($transports['senders']);
        }

        $container
            ->getDefinition('messenger.bus.shopware')
            ->replaceArgument(1, $mapped);
    }
}
