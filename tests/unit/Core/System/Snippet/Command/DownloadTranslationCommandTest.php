<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Command;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\DownloadTranslationCommand;
use Shopware\Core\System\Snippet\DataTransfer\Language\LanguageCollection;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Shopware\Core\System\Snippet\Service\AbstractTranslationLoader;
use Shopware\Core\System\Snippet\Service\TranslationArchiveLoader;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(DownloadTranslationCommand::class)]
class DownloadTranslationCommandTest extends TestCase
{
    /**
     * @var AbstractTranslationLoader&MockObject
     */
    private AbstractTranslationLoader $translationLoader;

    private TranslationArchiveLoader&Stub $archiveLoader;

    protected function setUp(): void
    {
        $this->translationLoader = $this->createMock(AbstractTranslationLoader::class);
        $this->archiveLoader = static::createStub(TranslationArchiveLoader::class);
    }

    public function testDownloadsAllConfiguredLocalesWithoutDatabaseActivation(): void
    {
        $archiveLoader = $this->createMock(TranslationArchiveLoader::class);
        $archiveLoader->expects($this->never())->method('download');
        $this->archiveLoader = $archiveLoader;
        $this->translationLoader->expects($this->exactly(2))
            ->method('download')
            ->with(static::callback(
                static fn (string $locale): bool => \in_array($locale, ['de-DE', 'es-ES'], true)
            ));

        $tester = new CommandTester($this->createCommand(null));

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
    }

    public function testDownloadsAllConfiguredLocalesFromTheArchive(): void
    {
        $archiveLoader = $this->createMock(TranslationArchiveLoader::class);
        $archiveLoader->expects($this->once())->method('download')->with(['de-DE', 'es-ES']);
        $this->archiveLoader = $archiveLoader;
        $this->translationLoader->expects($this->never())->method('download');

        $tester = new CommandTester($this->createCommand(new Uri('http://localhost:8000/manifest.json')));

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        static::assertStringContainsString('Downloaded the translations from the translation archive.', $tester->getDisplay());
    }

    public function testDownloadsEachLocaleWhenTheArchiveCannotBeUsed(): void
    {
        $this->archiveLoader->method('download')
            ->willThrowException(SnippetException::translationArchiveUnavailable('the archive is no zip file'));
        $this->translationLoader->expects($this->exactly(2))->method('download');

        $tester = new CommandTester($this->createCommand(new Uri('http://localhost:8000/manifest.json')));

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        static::assertStringContainsString(
            'The translation archive cannot be used: the archive is no zip file. Downloading the translation files one by one.',
            $tester->getDisplay(),
        );
    }

    private function createCommand(?Uri $archiveManifestUrl): DownloadTranslationCommand
    {
        return new DownloadTranslationCommand(
            $this->translationLoader,
            new TranslationConfig(
                new Uri('http://localhost:8000'),
                ['de-DE', 'es-ES'],
                [],
                new LanguageCollection(),
                new PluginMappingCollection(),
                new Uri('http://localhost:8000/metadata.json'),
                [],
                archiveManifestUrl: $archiveManifestUrl,
            ),
            $this->archiveLoader,
        );
    }
}
