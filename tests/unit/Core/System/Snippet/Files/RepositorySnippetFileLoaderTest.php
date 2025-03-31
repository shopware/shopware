<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Files;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Shopware\Core\System\Snippet\Files\RepositorySnippetFileLoader;
use Shopware\Core\System\Snippet\Files\GenericSnippetFile;

class RepositorySnippetFileLoaderTest extends TestCase
{
    private string $repositoryPath;

    protected function setUp(): void
    {
        $this->repositoryPath = __DIR__ . '/../../../../../custom/translations';
    }

    public function testLoadSnippetFilesFromRepository(): void
    {
        $finderMock = $this->createMock(Finder::class);
        $fileInfoMock = $this->createMock(SplFileInfo::class);

        $fileInfoMock->method('getPathname')
            ->willReturn($this->repositoryPath . '/translations/fr-FR/messages.json');
        $fileInfoMock->method('getFilename')
            ->willReturn('messages.json');

        $finderMock->method('getIterator')
            ->willReturn(new \ArrayIterator([$fileInfoMock]));

        $loader = new RepositorySnippetFileLoader($this->repositoryPath);

        $snippetFiles = $loader->loadSnippetFilesFromRepository();

        $this->assertCount(1, $snippetFiles);
        $this->assertInstanceOf(GenericSnippetFile::class, $snippetFiles[0]);
        $this->assertEquals('messages.fr-FR', $snippetFiles[0]->getName());
        $this->assertEquals('fr-FR', $snippetFiles[0]->getIso());
    }

    public function testEnsureDirectoryExists(): void
    {
        $loader = new RepositorySnippetFileLoader($this->repositoryPath);

        if (is_dir($this->repositoryPath)) {
            rmdir($this->repositoryPath);
        }

        $loader->ensureDirectoryExists($this->repositoryPath);

        $this->assertDirectoryExists($this->repositoryPath);

        rmdir($this->repositoryPath);
    }

    public function testGetIsoCodeFromFilePath(): void
    {
        $loader = new RepositorySnippetFileLoader($this->repositoryPath);

        $pathWithIso = $this->repositoryPath . '/translations/fr-FR/messages.json';
        $pathWithoutIso = $this->repositoryPath . '/messages.json';

        $this->assertEquals('fr-FR', $loader->getIsoCodeFromFilePath($pathWithIso));
        $this->assertEquals('Repository', $loader->getIsoCodeFromFilePath($pathWithoutIso));
    }
}
