<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Administration\Administration;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\ValidateSnippetsCommand;
use Shopware\Storefront\Storefront;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * @phpstan-import-type Snippets from ValidateSnippetsCommand
 */
#[Package('discovery')]
class SnippetFileHandler
{
    /**
     * @internal
     */
    public function __construct(private readonly Filesystem $filesystem)
    {
    }

    /**
     * @return Snippets
     */
    public function openJsonFile(string $path): array
    {
        try {
            $fileContents = $this->filesystem->readFile($path);
        } catch (\Throwable) {
            throw SnippetException::jsonNotFound();
        }

        try {
            $json = json_decode($fileContents, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw SnippetException::invalidSnippetFile($path, $e);
        }

        return $json;
    }

    /**
     * @param Snippets $content
     */
    public function writeJsonFile(string $path, array $content): void
    {
        try {
            $json = \json_encode($content, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            throw SnippetException::invalidSnippetFile($path, $e);
        }

        $json = str_replace('    ', '  ', $json); // Workaround because of wrong indentation
        $this->filesystem->dumpFile($path, $json);
    }

    /**
     * @return list<string>
     */
    public function findAdministrationSnippetFiles(): array
    {
        if (!($bundleDir = $this->getBundleDir(Administration::class))) {
            return [];
        }

        return $this->findSnippetFilesByPath($bundleDir . '/Resources/app/*/src/');
    }

    /**
     * @return list<string>
     */
    public function findStorefrontSnippetFiles(): array
    {
        if (!($bundleDir = $this->getBundleDir(Storefront::class))) {
            return [];
        }

        return $this->findSnippetFilesByPath($bundleDir . '/Resources/snippet/');
    }

    private function getBundleDir(string $bundleClass): ?string
    {
        if (!class_exists($bundleClass)) {
            return null;
        }

        return \dirname((string) (new \ReflectionClass($bundleClass))->getFileName());
    }

    /**
     * @return list<string>
     */
    private function findSnippetFilesByPath(string $path): array
    {
        $finder = (new Finder())
            ->files()
            ->in($path)
            ->ignoreUnreadableDirs();

        $finder->name('/[a-z]{2}-[A-Z]{2}(?:\.base)?\.json$/');

        $iterator = $finder->getIterator();
        $files = [];

        foreach ($iterator as $file) {
            $files[] = $file->getRealPath();
        }

        return $files;
    }
}
