<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @Decoratable
 */
class SnippetFileLoader implements SnippetFileLoaderInterface
{
    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var array
     */
    private $pluginAuthors;

    public function __construct(KernelInterface $kernel, Connection $connection)
    {
        $this->kernel = $kernel;
        // use Connection directly as this gets executed so early on kernel boot
        // using the DAL would result in CircularReferences
        $this->connection = $connection;
    }

    public function loadSnippetFilesIntoCollection(SnippetFileCollection $snippetFileCollection): void
    {
        foreach ($this->kernel->getBundles() as $bundle) {
            if (!$bundle instanceof Bundle) {
                continue;
            }

            $snippetDir = $bundle->getPath() . '/Resources/snippet';

            if (!is_dir($snippetDir)) {
                continue;
            }

            foreach ($this->loadSnippetFilesInDir($snippetDir, $bundle) as $snippetFile) {
                if ($snippetFileCollection->hasFileForPath($snippetFile->getPath())) {
                    continue;
                }

                $snippetFileCollection->add($snippetFile);
            }
        }
    }

    /**
     * @return SnippetFileInterface[]
     */
    private function loadSnippetFilesInDir(string $snippetDir, Bundle $bundle): array
    {
        $finder = new Finder();
        $finder->in($snippetDir)
            ->files()
            ->name('*.json');

        $snippetFiles = [];

        foreach ($finder->getIterator() as $fileInfo) {
            $nameParts = explode('.', $fileInfo->getFilenameWithoutExtension());

            $snippetFile = null;
            switch (count($nameParts)) {
                case 2:
                    $snippetFile = new GenericSnippetFile(
                        implode('.', $nameParts),
                        $fileInfo->getPathname(),
                        $nameParts[1],
                        $this->getAuthorFromBundle($bundle),
                        false
                    );

                    break;
                case 3:
                    $snippetFile = new GenericSnippetFile(
                        implode('.', [$nameParts[0], $nameParts[1]]),
                        $fileInfo->getPathname(),
                        $nameParts[1],
                        $this->getAuthorFromBundle($bundle),
                        $nameParts[2] === 'base'
                    );

                    break;
            }

            if ($snippetFile) {
                $snippetFiles[] = $snippetFile;
            }
        }

        return $snippetFiles;
    }

    private function getAuthorFromBundle(Bundle $bundle): string
    {
        if (!$bundle instanceof Plugin) {
            return 'Shopware';
        }

        return $this->getPluginAuthors()[get_class($bundle)] ?? '';
    }

    private function getPluginAuthors(): array
    {
        if (!$this->pluginAuthors) {
            $authors = $this->connection->fetchAll('
            SELECT `base_class` AS `baseClass`, `author`
            FROM `plugin`
        ');

            $this->pluginAuthors = FetchModeHelper::keyPair($authors);
        }

        return $this->pluginAuthors;
    }
}
