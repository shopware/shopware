<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Administration\Administration;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Storefront;
use Symfony\Component\Finder\Finder;

/**
 * @phpstan-type Snippets array<string, string|mixed>
 */
#[Package('discovery')]
class SnippetFileHandler
{
    /**
     * @return Snippets
     */
    public function openJsonFile(string $path): array
    {
        $fileContents = file_get_contents($path);

        if ($fileContents === false) {
            throw SnippetException::jsonNotFound();
        }

        $json = json_decode($fileContents, true, 512, \JSON_THROW_ON_ERROR);

        $jsonError = json_last_error();
        if ($jsonError !== 0) {
            throw SnippetException::invalidSnippetFile($path, new \RuntimeException(json_last_error_msg(), $jsonError));
        }

        return $json;
    }

    /**
     * @param Snippets $content
     */
    public function writeJsonFile(string $path, array $content): void
    {
        $json = \json_encode(
            $content,
            \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES
        ) ?: '';
        $json = str_replace('    ', '  ', $json); // Workaround because of wrong indentation
        file_put_contents($path, $json);
    }

    /**
     * @return string[]
     */
    public function findAdministrationSnippetFiles(): array
    {
        if (!($bundleDir = $this->getBundleDir(Administration::class))) {
            return [];
        }

        return $this->findSnippetFilesByPath($bundleDir . '/Resources/app/*/src/');
    }

    /**
     * @return string[]
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
     * @return string[]
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
