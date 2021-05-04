<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class FilesystemConfigMigrationCompilerPass implements CompilerPassInterface
{
    private const MIGRATED_FS = ['theme', 'asset', 'sitemap'];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::MIGRATED_FS as $fs) {
            $key = sprintf('shopware.filesystem.%s', $fs);
            $urlKey = $key . '.url';
            $typeKey = $key . '.type';
            $configKey = $key . '.config';
            if ($container->hasParameter($typeKey)) {
                continue;
            }

            // 6.1 always refers to the main shop url on theme, asset and sitemap.
            $container->setParameter($urlKey, '');
            $container->setParameter($key, '%shopware.filesystem.public%');
            $container->setParameter($typeKey, '%shopware.filesystem.public.type%');
            $container->setParameter($configKey, '%shopware.filesystem.public.config%');
        }

        if (!$container->hasParameter('shopware.filesystem.public.url')) {
            $container->setParameter('shopware.filesystem.public.url', '%shopware.cdn.url%');
        }
    }
}
