<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Snippet;

use League\Flysystem\Filesystem;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('discovery')]
trait SnippetFileTrait
{
    private function createSnippetFixtures(Filesystem $filesystem, TranslationLoader $loader): void
    {
        $platformPath = Path::join($loader->getLocalePath('es-ES'), 'Platform');
        $activePluginPath = Path::join($loader->getLocalePath('es-ES'), 'Plugins', 'activePlugin');
        $inactivePluginPath = Path::join($loader->getLocalePath('es-ES'), 'Plugins', 'inactivePlugin');

        $translationFiles = [
            Path::join($platformPath, 'storefront.json') => '{"shop_storefront": "Platform storefront"}',
            Path::join($platformPath, 'administration.json') => '{"shop_administration": "Platform admin"}',
            Path::join($platformPath, 'messages.es-ES.base.json') => '{"shop_base": "Platform base"}',
            Path::join($activePluginPath, 'storefront.json') => '{"plugin_storefront": "Plugin storefront"}',
            Path::join($activePluginPath, 'administration.json') => '{"plugin_administration": "Plugin admin"}',
            Path::join($activePluginPath, 'messages.es-ES.base.json') => '{"plugin_base": "Platform base"}',
            Path::join($inactivePluginPath, 'storefront.json') => '{"inactive_storefront": "Inactive plugin"}',
        ];

        foreach ($translationFiles as $file => $content) {
            $filesystem->write($file, $content);
        }
    }
}
