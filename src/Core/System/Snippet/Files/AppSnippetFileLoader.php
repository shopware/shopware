<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

#[Package('system-settings')]
class AppSnippetFileLoader
{
    /**
     * @internal
     */
    public function __construct(private readonly string $projectDir)
    {
    }

    /**
     * @param bool $isAbsolutePath is used for remote app loading in cloud environments,
     *                             therefore it's always false for local apps
     *
     * @return GenericSnippetFile[]
     */
    public function loadSnippetFilesFromApp(string $author, string $appPath, bool $isAbsolutePath = false): array
    {
        $snippetDir = $this->getSnippetDir($appPath, $isAbsolutePath);
        if (!is_dir($snippetDir)) {
            return [];
        }

        $finder = $this->getSnippetFinder($snippetDir);

        $snippetFiles = [];

        foreach ($finder->getIterator() as $fileInfo) {
            $nameParts = explode('.', $fileInfo->getFilenameWithoutExtension());

            $snippetFile = $this->createSnippetFile($nameParts, $fileInfo, $author);

            if ($snippetFile) {
                $snippetFiles[] = $snippetFile;
            }
        }

        return $snippetFiles;
    }

    private function getSnippetFinder(string $snippetDir): Finder
    {
        $finder = new Finder();
        $finder->in($snippetDir)
            ->files()
            ->name('*.json');

        return $finder;
    }

    /**
     * @param array<string> $nameParts
     */
    private function createSnippetFile(array $nameParts, SplFileInfo $fileInfo, string $author): ?GenericSnippetFile
    {
        return match (\count($nameParts)) {
            2 => $this->getSnippetFile($nameParts, $fileInfo, $author),
            3 => $this->getBaseSnippetFile($nameParts, $fileInfo, $author),
            default => null,
        };
    }

    /**
     * @param array<string> $nameParts
     */
    private function getSnippetFile(array $nameParts, SplFileInfo $fileInfo, string $author): GenericSnippetFile
    {
        return new GenericSnippetFile(
            implode('.', $nameParts),
            $fileInfo->getPathname(),
            $nameParts[1],
            $author,
            false,
            ''
        );
    }

    /**
     * @param array<string> $nameParts
     */
    private function getBaseSnippetFile(array $nameParts, SplFileInfo $fileInfo, string $author): GenericSnippetFile
    {
        return new GenericSnippetFile(
            implode('.', [$nameParts[0], $nameParts[1]]),
            $fileInfo->getPathname(),
            $nameParts[1],
            $author,
            $nameParts[2] === 'base',
            ''
        );
    }

    private function getSnippetDir(string $path, bool $isAbsolute): string
    {
        // add project path if path is not absolute already
        if (!$isAbsolute) {
            $path = $this->projectDir . '/' . $path;
        }

        return $path . '/Resources/snippet';
    }
}
