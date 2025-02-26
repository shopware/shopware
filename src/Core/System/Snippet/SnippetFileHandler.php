<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

#[Package('discovery')]
class SnippetFileHandler
{
    public function openJsonFile(string $path): array
    {
        $json = json_decode(file_get_contents($path), true);

        $jsonError = json_last_error();
        if ($jsonError !== 0) {
            throw new \RuntimeException(\sprintf('Invalid JSON in snippet file at path \'%s\' with code \'%d\'', $path, $jsonError));
        }

        return $json;
    }

    public function writeJsonFile(string $path, array $content): void
    {
        $json = json_encode($content, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

        file_put_contents($path, $json);
    }

    /**
     * @return array<int, string>
     */
    public function findBundleSnippetFiles(BundleInterface $bundle): array
    {
        $storefrontSnippets = [];
        if (is_dir($bundle->getPath() . '/Resources/snippet/')) {
            $storefrontSnippets = $this->findSnippetFilesByPath($bundle->getPath() . '/Resources/snippet/');
        }

        $administrationSnippets = [];
        if (is_dir($bundle->getPath() . '/Resources/app/*/src/')) {
            $administrationSnippets = $this->findSnippetFilesByPath($bundle->getPath() . '/Resources/app/*/src/');
        }

        return array_merge(...$storefrontSnippets, ...$administrationSnippets);
    }

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
