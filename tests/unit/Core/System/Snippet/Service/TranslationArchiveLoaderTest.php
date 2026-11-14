<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Exception\MalformedUriException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use League\Flysystem\Filesystem as FlySystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Snippet\DataTransfer\Language\Language;
use Shopware\Core\System\Snippet\DataTransfer\Language\LanguageCollection;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Shopware\Core\System\Snippet\Service\AbstractTranslationLoader;
use Shopware\Core\System\Snippet\Service\TranslationArchiveLoader;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TranslationArchiveLoader::class)]
class TranslationArchiveLoaderTest extends TestCase
{
    private const MANIFEST_URL = 'https://example.com/releases/latest/download/manifest.json';

    private Filesystem $filesystem;

    private string $cacheDir;

    private FlySystem $translationWriter;

    private MockHandler $responses;

    /**
     * @var list<string>
     */
    private array $requestedUrls = [];

    private ?Uri $manifestUrl;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->cacheDir = Path::join(sys_get_temp_dir(), 'translation-archive-test-' . Uuid::randomHex());
        $this->filesystem->mkdir($this->cacheDir);
        $this->translationWriter = new FlySystem(new InMemoryFilesystemAdapter());
        $this->responses = new MockHandler();
        $this->manifestUrl = new Uri(self::MANIFEST_URL);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->cacheDir);
    }

    public function testDownloadWritesTheConfiguredFilesOfTheRequestedLocales(): void
    {
        $archive = $this->createArchive([
            'translations/es-ES/Platform/Core/messages.json' => '{"core":"es"}',
            'translations/es-ES/Platform/Storefront/storefront.json' => '{"storefront":"es"}',
            'translations/es-ES/Plugins/SwagPublisher/Administration/administration.json' => '{"publisher":"es"}',
            'translations/es-ES/Plugins/SwagUnconfigured/Storefront/storefront.json' => '{}',
            'translations/it-IT/Platform/Storefront/storefront.json' => '{}',
            'crowdin-metadata.json' => '{}',
        ]);
        $this->respondWithArchive($archive);

        $this->createLoader()->download(['es-ES']);

        static::assertSame([
            'translation/locale/es-ES/Platform/messages.es-ES.base.json' => '{"core":"es"}',
            'translation/locale/es-ES/Platform/storefront.json' => '{"storefront":"es"}',
            'translation/locale/es-ES/Plugins/SwagPublisher/administration.json' => '{"publisher":"es"}',
        ], $this->writtenFiles());
        static::assertSame(
            [self::MANIFEST_URL, 'https://example.com/releases/latest/download/translations.zip'],
            $this->requestedUrls,
        );
        static::assertSame([], glob(Path::join($this->cacheDir, 'translation-archive', '*')));
    }

    public function testDownloadRejectsAnArchiveThatDoesNotMatchItsManifest(): void
    {
        $archive = $this->createArchive(['translations/es-ES/Platform/Storefront/storefront.json' => '{}']);
        $this->responses->append(
            new Response(200, [], $this->manifest(hash('sha256', 'another archive'))),
            new Response(200, [], $archive),
        );

        $this->expectExceptionObject(SnippetException::translationArchiveUnavailable(
            '"https://example.com/releases/latest/download/translations.zip" does not match the checksum of its manifest'
        ));

        try {
            $this->createLoader()->download(['es-ES']);
        } finally {
            static::assertSame([], $this->writtenFiles());
            static::assertSame([], glob(Path::join($this->cacheDir, 'translation-archive', '*')));
        }
    }

    public function testDownloadRejectsAFileThatIsNoZipArchive(): void
    {
        $this->respondWithArchive('not a zip file');

        $this->expectExceptionObject(SnippetException::translationArchiveUnavailable('the archive is no zip file'));

        $this->createLoader()->download(['es-ES']);
    }

    public function testDownloadFailsWhenTheManifestCannotBeRead(): void
    {
        $this->responses->append(new Response(404));

        $this->expectExceptionObject(SnippetException::translationArchiveUnavailable(\sprintf('the manifest "%s" cannot be read', self::MANIFEST_URL)));

        $this->createLoader()->download(['es-ES']);
    }

    public function testDownloadFailsWhenTheManifestNamesNoArchive(): void
    {
        $this->responses->append(new Response(200, [], '{"commit":"89e860b"}'));

        $this->expectExceptionObject(SnippetException::translationArchiveUnavailable(\sprintf('the manifest "%s" names no archive', self::MANIFEST_URL)));

        $this->createLoader()->download(['es-ES']);
    }

    public function testDownloadFailsWhenTheArchiveCannotBeDownloaded(): void
    {
        $this->responses->append(
            new Response(200, [], $this->manifest(hash('sha256', ''))),
            new Response(503),
        );

        $this->expectExceptionObject(SnippetException::translationArchiveUnavailable(
            '"https://example.com/releases/latest/download/translations.zip" cannot be downloaded'
        ));

        $this->createLoader()->download(['es-ES']);
    }

    public function testDownloadFailsWithoutAConfiguredManifest(): void
    {
        $this->manifestUrl = null;

        $this->expectExceptionObject(SnippetException::translationArchiveUnavailable('no archive manifest is configured'));

        $this->createLoader()->download(['es-ES']);
    }

    public function testDownloadRejectsALocaleThatIsNotConfigured(): void
    {
        $this->expectExceptionObject(SnippetException::languageDoesNotExist('xx-XX'));

        try {
            $this->createLoader()->download(['es-ES', 'xx-XX']);
        } finally {
            static::assertSame([], $this->requestedUrls);
        }
    }

    public function testDownloadAcceptsAnArchiveThatCoversTheNewestUpdate(): void
    {
        $archive = $this->createArchive(['translations/es-ES/Platform/Storefront/storefront.json' => '{"storefront":"es"}']);
        $this->respondWithArchive($archive, committedAt: '2026-11-10T12:34:56+01:00');

        $this->createLoader()->download(['es-ES'], new \DateTimeImmutable('2026-11-10T11:34:56.436+00:00'));

        static::assertSame(['translation/locale/es-ES/Platform/storefront.json' => '{"storefront":"es"}'], $this->writtenFiles());
    }

    #[DataProvider('provideArchivesOlderThanTheNewestUpdate')]
    public function testDownloadRejectsAnArchiveOlderThanTheNewestUpdate(?string $committedAt): void
    {
        $archive = $this->createArchive(['translations/es-ES/Platform/Storefront/storefront.json' => '{}']);
        $this->respondWithArchive($archive, committedAt: $committedAt);

        $this->expectExceptionObject(SnippetException::translationArchiveUnavailable('the archive is older than the translation metadata'));

        try {
            $this->createLoader()->download(['es-ES'], new \DateTimeImmutable('2026-11-10T11:34:57+00:00'));
        } finally {
            static::assertSame([self::MANIFEST_URL], $this->requestedUrls);
            static::assertSame([], $this->writtenFiles());
        }
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function provideArchivesOlderThanTheNewestUpdate(): iterable
    {
        yield 'built before the update' => ['2026-11-10T12:34:56+01:00'];
        yield 'without a commit date' => [null];
        yield 'with an unparsable commit date' => ['yesterday'];
    }

    public function testDownloadConvertsAnInvalidArchiveFileOfTheManifest(): void
    {
        $this->responses->append(new Response(200, [], $this->manifest(hash('sha256', ''), file: 'http:///translations.zip')));

        try {
            $this->createLoader()->download(['es-ES']);
            static::fail('An invalid archive file must make the archive unavailable');
        } catch (SnippetException $e) {
            static::assertSame('SYSTEM__TRANSLATION_ARCHIVE_UNAVAILABLE', $e->getErrorCode());
            static::assertInstanceOf(MalformedUriException::class, $e->getPrevious());
        }
    }

    public function testDownloadConvertsAnUnusableCacheDirectory(): void
    {
        $this->respondWithArchive($this->createArchive(['translations/es-ES/Platform/Storefront/storefront.json' => '{}']));
        $cacheRoot = $this->cacheDir;
        $this->cacheDir = Path::join($cacheRoot, 'not-a-directory');
        $this->filesystem->dumpFile($this->cacheDir, '');

        try {
            $this->createLoader()->download(['es-ES']);
            static::fail('A cache directory that cannot be created must make the archive unavailable');
        } catch (SnippetException $e) {
            static::assertSame('SYSTEM__TRANSLATION_ARCHIVE_UNAVAILABLE', $e->getErrorCode());
            static::assertInstanceOf(IOException::class, $e->getPrevious());
        } finally {
            $this->filesystem->remove($cacheRoot);
        }
    }

    private function createLoader(): TranslationArchiveLoader
    {
        $handler = HandlerStack::create($this->responses);
        $handler->push(Middleware::tap(function (RequestInterface $request): void {
            $this->requestedUrls[] = (string) $request->getUri();
        }));

        $translationLoader = static::createStub(AbstractTranslationLoader::class);
        $translationLoader->method('getLocalePath')
            ->willReturnCallback(static fn (string $locale): string => Path::join(AbstractTranslationLoader::TRANSLATION_DIR, AbstractTranslationLoader::TRANSLATION_LOCALE_SUB_DIR, $locale));

        return new TranslationArchiveLoader(
            new Client(['handler' => $handler]),
            $this->translationWriter,
            $translationLoader,
            new TranslationConfig(
                new Uri('https://example.com/translations'),
                ['es-ES', 'it-IT'],
                ['SwagPublisher'],
                new LanguageCollection([new Language('es-ES', 'Español'), new Language('it-IT', 'Italiano')]),
                new PluginMappingCollection(),
                new Uri('https://example.com/metadata.json'),
                [],
                archiveManifestUrl: $this->manifestUrl,
            ),
            $this->filesystem,
            $this->cacheDir,
        );
    }

    /**
     * @param array<string, string> $contentByPath
     */
    private function createArchive(array $contentByPath): string
    {
        $archivePath = Path::join($this->cacheDir, 'fixture.zip');

        $zip = new \ZipArchive();
        $zip->open($archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($contentByPath as $path => $content) {
            $zip->addFromString($path, $content);
        }
        $zip->close();

        $archive = $this->filesystem->readFile($archivePath);
        $this->filesystem->remove($archivePath);

        return $archive;
    }

    private function respondWithArchive(string $archive, ?string $committedAt = null): void
    {
        $this->responses->append(
            new Response(200, [], $this->manifest(hash('sha256', $archive), committedAt: $committedAt)),
            new Response(200, [], $archive),
        );
    }

    private function manifest(string $checksum, string $file = 'translations.zip', ?string $committedAt = null): string
    {
        $manifest = ['commit' => '89e860b', 'archive' => ['file' => $file, 'sha256' => $checksum]];

        if ($committedAt !== null) {
            $manifest['committedAt'] = $committedAt;
        }

        return json_encode($manifest, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, string>
     */
    private function writtenFiles(): array
    {
        $files = [];
        foreach ($this->translationWriter->listContents('/', true) as $item) {
            if ($item->isFile()) {
                $files[$item->path()] = $this->translationWriter->read($item->path());
            }
        }
        ksort($files);

        return $files;
    }
}
