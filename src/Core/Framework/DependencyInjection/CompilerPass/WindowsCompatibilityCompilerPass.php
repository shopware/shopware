<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use League\OAuth2\Server\CryptKey;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('core')]
class WindowsCompatibilityCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (\PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        $this->skipCryptKeyPermissionCheck($container);
    }

    /**
     * Permission check always fails in Windows because it has a different permission system, so we
     * disable have to disable it.
     */
    public function skipCryptKeyPermissionCheck(ContainerBuilder $container): void
    {
        $definitions = $container->getDefinitions();
        $cryptKeyDefinitions = array_filter(
            $definitions,
            static fn (Definition $definition) => $definition->getClass() === CryptKey::class
        );

        foreach ($cryptKeyDefinitions as $definition) {
            $arguments = $definition->getArguments();

            if (!\array_key_exists('$passPhrase', $arguments)) {
                $definition->setArgument('$passPhrase', null);
            }

            $definition->setArgument('$keyPermissionsCheck', false);
        }
    }
}
