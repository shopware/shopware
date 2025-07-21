<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Snippet;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Service\TranslationLoader;

/**
 * @internal
 */
#[Package('discovery')]
trait SnippetFileTrait
{
    private function createSnippetFiles(): void
    {
        $paths = [
            'platform' => TranslationLoader::TRANSLATION_DESTINATION . '/es-ES/Platform',
            'plugin' => TranslationLoader::TRANSLATION_DESTINATION . '/es-ES/Plugins/activePlugin',
        ];

        $files = [
            'administration.json',
            'storefront.json',
            'messages.es-ES.base.json',
        ];

        foreach ($paths as $scope => $path) {
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }

            if ($scope === 'platform') {
                $snippet = ['shop' => 'Demo Shop'];
            } else {
                $snippet = ['plugin' => 'activePlugin'];
            }

            foreach ($files as $file) {
                $filePath = $path . '/' . $file;
                if (!is_file($filePath)) {
                    file_put_contents($filePath, json_encode($snippet, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
                }
            }
        }
    }

    private function cleanupSnippetFiles(): void
    {
        $dir = TranslationLoader::TRANSLATION_DESTINATION . '/es-ES';

        if (!\is_dir($dir)) {
            // If the directory does not exist, there's nothing to clean up.
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            static::assertInstanceOf(\SplFileInfo::class, $item);

            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
