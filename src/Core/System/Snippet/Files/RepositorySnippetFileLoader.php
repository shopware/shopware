<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

use Symfony\Component\Finder\Finder;

class RepositorySnippetFileLoader
{
    private string $repositoryPath;

    public function __construct(string $repositoryPath)
    {
        $this->repositoryPath = $repositoryPath;
    }

    /**
     * @return AbstractSnippetFile[]
     */
    public function loadSnippetFilesFromRepository(): array
    {
        $excludedSnippetSets = ["de-DE", "en-GB", "ach-UG"];

        $finder = new Finder();
        $finder->in($this->repositoryPath)
            ->files()
            ->name('messages.json')
            ->name('storefront.json')
            ->path('/')
            ->exclude($excludedSnippetSets);

        $snippetFiles = [];

        foreach ($finder->getIterator() as $fileInfo) {
            $iso = $this->getIsoCodeFromFilePath($fileInfo->getPathname());
            $filename = pathinfo($fileInfo->getPathname(), PATHINFO_FILENAME) . '.' . $iso;

            $snippetFile = new GenericSnippetFile(
                $filename,
                $fileInfo->getPathname(),
                $iso,
                'Crowdin',
                $fileInfo->getFilename() === 'messages.json',
                'Repository'
            );
            $snippetFiles[] = $snippetFile;

        }

        return $snippetFiles;
    }
    function ensureDirectoryExists(string $directoryPath): void
    {
        if (!is_dir($directoryPath)) {
            mkdir($directoryPath, 0777, true);
        }
    }

    function getIsoCodeFromFilePath(string $filePath): ?string
    {
        $pattern = '#/translations/([a-z]{2,3}-[A-Z]{2})/#';

        if (preg_match($pattern, $filePath, $matches)) {
            return $matches[1];
        }

        return 'Repository';
    }
}
