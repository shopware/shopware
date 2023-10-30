<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('core')]
class DefaultTransportCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // the default transport is defined by the parameter `messenger.default_transport_name`
        $defaultName = $container->getParameter('messenger.default_transport_name');
        if (!\is_string($defaultName)) {
            throw new \TypeError('Parameter `messenger.default_transport_name` should be a string.');
        }
        $id = 'messenger.transport.' . $defaultName;
        $container->addAliases(['messenger.default_transport' => $id]);

        $messenger = $this->findMessenger(
            $container->getExtensionConfig('framework')
        );

        if (!$messenger || !\array_key_exists('routing', $messenger)) {
            return;
        }

        $container
            ->getDefinition('messenger.bus.shopware')
            ->replaceArgument(1, $messenger['routing']);
    }

    /**
     * @param array<array<string, mixed>> $config
     *
     * @return array<string, mixed>|null
     */
    private function findMessenger(array $config): array|null
    {
        if (\array_key_exists('messenger', $config)) {
            return $config['messenger'];
        }

        foreach ($config as $value) {
            if (!\is_array($value)) {
                continue;
            }
            $nested = $this->findMessenger($value);

            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }
}
