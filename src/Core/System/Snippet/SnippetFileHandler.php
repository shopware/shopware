<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Symfony\Component\Finder\Finder;

class SnippetFileHandler
{
    public function openJsonFile(string $path): array
    {
        $json = json_decode(file_get_contents($path), true);

        $jsonError = json_last_error();
        if ($jsonError !== 0) {
            throw new \RuntimeException(sprintf('Invalid JSON in snippet file at path \'%s\' with code \'%d\'', $path, $jsonError));
        }

        return $json;
    }

    public function writeJsonFile(string $path, array $content): void
    {
        $json = json_encode($content, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

        file_put_contents($path, $json);
    }

    public function findAdministrationSnippetFiles(): array
    {
        return $this->findSnippetFilesByPath(__DIR__ . '/../../../Administration/Resources/app/*/src/');
    }

    public function findStorefrontSnippetFiles(): array
    {
        return $this->findSnippetFilesByPath(__DIR__ . '/../../../Storefront/Resources/snippet/');
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
