<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;

#[Package('discovery')]
class SnippetFileLoader implements SnippetFileLoaderInterface
{
    private const SNIPPET_FILE_NAMES = ['core.json', 'storefront.json'];

    /**
     * @internal
     */
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly Connection $connection,
        private readonly AppSnippetFileLoader $appSnippetFileLoader,
        private readonly ActiveAppsLoader $activeAppsLoader,
    ) {
    }

    public function loadSnippetFilesIntoCollection(SnippetFileCollection $snippetFileCollection): void
    {
        $this->loadPluginSnippets($snippetFileCollection);

        $this->loadAppSnippets($snippetFileCollection);

        $this->loadCoreSnippets($snippetFileCollection);
    }

    private function loadCoreSnippets(SnippetFileCollection $snippetFileCollection): void
    {
        $finder = new Finder();
        $finder->in(TranslationLoader::TRANSLATION_DESTINATION)
            ->files()
            ->name(self::SNIPPET_FILE_NAMES)
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->ignoreUnreadableDirs();

        foreach ($finder->getIterator() as $fileInfo) {
            $relativePath = $fileInfo->getRelativePath();
            $parts = explode(DIRECTORY_SEPARATOR, $relativePath);

            if ($parts[1] === 'Plugins') {
                $technicalName = $parts[2];
            } else {
                $technicalName = 'Platform';
            }

            $locale = $parts[0];
            $isBase = $fileInfo->getFilenameWithoutExtension() === 'core';

            $snippetFile = new GenericSnippetFile(
                $fileInfo->getFilename(),
                $fileInfo->getPathname(),
                $locale,
                'Shopware',
                $isBase,
                $technicalName,
            );

            $snippetFileCollection->add($snippetFile);
        }
    }

    /*
     * todo: rename this method to a generic scope because it loads also shopware snippets
     */
    private function loadPluginSnippets(SnippetFileCollection $snippetFileCollection): void
    {
        try {
            /** @var array<string, string> $authors */
            $authors = $this->connection->fetchAllKeyValue('
                SELECT `base_class` AS `baseClass`, `author`
                FROM `plugin`
            ');
        } catch (Exception) {
            // to get it working in setup without a database connection
            $authors = [];
        }

        foreach ($this->kernel->getBundles() as $name => $bundle) {
            // todo: use constants or enums
            // skip Administration bundle because we are in the storefront scope
            if (!$bundle instanceof Bundle || $name === 'Administration') {
                continue;
            }

            $snippetDir = $bundle->getPath() . '/Resources';

            if (!is_dir($snippetDir)) {
                continue;
            }

            foreach ($this->loadSnippetFilesInDir($snippetDir, $bundle, $authors) as $snippetFile) {
                if ($snippetFileCollection->hasFileForPath($snippetFile->getPath())) {
                    continue;
                }

                $snippetFileCollection->add($snippetFile);
            }
        }
    }

    private function loadAppSnippets(SnippetFileCollection $snippetFileCollection): void
    {
        foreach ($this->activeAppsLoader->getActiveApps() as $app) {
            $snippetFiles = $this->appSnippetFileLoader->loadSnippetFilesFromApp($app['author'] ?? '', $app['path']);
            foreach ($snippetFiles as $snippetFile) {
                $snippetFile->setTechnicalName($app['name']);
                $snippetFileCollection->add($snippetFile);
            }
        }
    }

    /**
     * @param array<string, string> $authors
     *
     * @return AbstractSnippetFile[]
     */
    private function loadSnippetFilesInDir(string $snippetDir, Bundle $bundle, array $authors): array
    {
        // load snippets
        $finder = new Finder();
        $finder->in($snippetDir)
            ->exclude('node_modules')
            ->files()
            ->path('/snippet/')
            ->name('*.json')
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->ignoreUnreadableDirs();

        $snippetFiles = [];

        foreach ($finder->getIterator() as $fileInfo) {
            $nameParts = explode('.', $fileInfo->getFilenameWithoutExtension());

            $snippetFile = null;
            switch (\count($nameParts)) {
                case 1:
                    $snippetFile = new GenericSnippetFile(
                        $nameParts[0],
                        $fileInfo->getPathname(),
                        $nameParts[0],
                        $this->getAuthorFromBundle($bundle, $authors),
                        false,
                        $bundle->getName()
                    );

                    break;
                case 2:
                    $snippetFile = new GenericSnippetFile(
                        implode('.', $nameParts),
                        $fileInfo->getPathname(),
                        $nameParts[1],
                        $this->getAuthorFromBundle($bundle, $authors),
                        false,
                        $bundle->getName()
                    );

                    break;
                case 3:
                    $snippetFile = new GenericSnippetFile(
                        implode('.', [$nameParts[0], $nameParts[1]]),
                        $fileInfo->getPathname(),
                        $nameParts[1],
                        $this->getAuthorFromBundle($bundle, $authors),
                        $nameParts[2] === 'base',
                        $bundle->getName()
                    );

                    break;
            }

            if ($snippetFile) {
                $snippetFiles[] = $snippetFile;
            }
        }

        return $snippetFiles;
    }

    /**
     * @param array<string, string> $authors
     */
    private function getAuthorFromBundle(Bundle $bundle, array $authors): string
    {
        if (!$bundle instanceof Plugin) {
            return 'Shopware';
        }

        return $authors[$bundle::class] ?? '';
    }
}
