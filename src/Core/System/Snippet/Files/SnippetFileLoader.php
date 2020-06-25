<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\PluginEntity;
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
     * @var EntityRepositoryInterface
     */
    private $pluginRepository;

    public function __construct(KernelInterface $kernel, EntityRepositoryInterface $pluginRepository)
    {
        $this->kernel = $kernel;
        $this->pluginRepository = $pluginRepository;
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
            return 'shopware AG';
        }

        $plugin = $this->getPluginEntityFromPluginBundle($bundle);

        if ($plugin) {
            return $plugin->getAuthor() ?? '';
        }

        return '';
    }

    private function getPluginEntityFromPluginBundle(Plugin $plugin): ?PluginEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('baseClass', get_class($plugin)));

        return $this->pluginRepository->search($criteria, Context::createDefaultContext())->first();
    }
}
