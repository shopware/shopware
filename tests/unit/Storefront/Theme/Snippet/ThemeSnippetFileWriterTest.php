<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Snippet;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\StorageAttributes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Snippet\ThemeConfigSnippetGenerator;
use Shopware\Storefront\Theme\Snippet\ThemeSnippetFileWriter;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeSnippetFileWriter::class)]
class ThemeSnippetFileWriterTest extends TestCase
{
    private Filesystem $privateFilesystem;

    private CacheInvalidator&MockObject $cacheInvalidator;

    private LoggerInterface&MockObject $logger;

    private ThemeSnippetFileWriter $writer;

    protected function setUp(): void
    {
        $this->privateFilesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->writer = new ThemeSnippetFileWriter(
            new ThemeConfigSnippetGenerator(),
            $this->privateFilesystem,
            $this->cacheInvalidator,
            $this->logger,
        );
    }

    public function testWritesOneSnippetFilePerLanguageAndInvalidatesTheAdminSnippetCache(): void
    {
        $this->cacheInvalidator->expects($this->once())->method('invalidate')->with(['admin-snippet'], true);
        $this->logger->expects($this->once())->method('warning')->with(static::stringContains('theme:migrate-translations SwagTheme'));

        $this->writer->write($this->createConfiguration([
            'sw-logo' => ['label' => ['en-GB' => 'Logo', 'de-DE' => 'Logo (DE)']],
        ]));

        static::assertSame(
            [
                'sw-theme' => [
                    'SwagTheme' => [
                        'default' => ['default' => ['default' => ['sw-logo' => ['label' => 'Logo']]]],
                    ],
                ],
            ],
            $this->readSnippetFile('snippets/administration/SwagTheme/en-GB.json'),
        );
        static::assertSame(
            [
                'sw-theme' => [
                    'SwagTheme' => [
                        'default' => ['default' => ['default' => ['sw-logo' => ['label' => 'Logo (DE)']]]],
                    ],
                ],
            ],
            $this->readSnippetFile('snippets/administration/SwagTheme/de-DE.json'),
        );
    }

    public function testThemeWithoutLegacyTranslationsWritesNothing(): void
    {
        $this->cacheInvalidator->expects($this->never())->method('invalidate');
        $this->logger->expects($this->never())->method('warning');

        $this->writer->write($this->createConfiguration([
            'sw-logo' => ['type' => 'media'],
        ]));

        static::assertFalse($this->privateFilesystem->directoryExists('snippets/administration/SwagTheme'));
    }

    public function testStaleFilesAreRemovedWhenTheThemeDroppedItsLegacyTranslations(): void
    {
        $this->privateFilesystem->write('snippets/administration/SwagTheme/fr.json', '{"stale": true}');
        $this->cacheInvalidator->expects($this->once())->method('invalidate')->with(['admin-snippet'], true);
        $this->logger->expects($this->never())->method('warning');

        $this->writer->write($this->createConfiguration([
            'sw-logo' => ['type' => 'media'],
        ]));

        static::assertFalse($this->privateFilesystem->directoryExists('snippets/administration/SwagTheme'));
    }

    public function testRewriteReplacesPreviouslyGeneratedFiles(): void
    {
        $this->privateFilesystem->write('snippets/administration/SwagTheme/fr.json', '{"stale": true}');
        $this->cacheInvalidator->expects($this->once())->method('invalidate')->with(['admin-snippet'], true);
        $this->logger->expects($this->once())->method('warning');

        $this->writer->write($this->createConfiguration([
            'sw-logo' => ['label' => ['en-GB' => 'Logo']],
        ]));

        static::assertFalse($this->privateFilesystem->fileExists('snippets/administration/SwagTheme/fr.json'));
        static::assertTrue($this->privateFilesystem->fileExists('snippets/administration/SwagTheme/en-GB.json'));
    }

    public function testRemoveDeletesGeneratedFilesAndInvalidatesTheCache(): void
    {
        $this->privateFilesystem->write('snippets/administration/SwagTheme/en-GB.json', '{}');
        $this->privateFilesystem->write('snippets/administration/OtherTheme/en-GB.json', '{}');
        $this->cacheInvalidator->expects($this->once())->method('invalidate')->with(['admin-snippet'], true);
        $this->logger->expects($this->never())->method('warning');

        $this->writer->remove('SwagTheme');

        static::assertFalse($this->privateFilesystem->directoryExists('snippets/administration/SwagTheme'));
        static::assertTrue($this->privateFilesystem->fileExists('snippets/administration/OtherTheme/en-GB.json'));
    }

    public function testRemoveWithoutGeneratedFilesIsANoOp(): void
    {
        $this->cacheInvalidator->expects($this->never())->method('invalidate');
        $this->logger->expects($this->never())->method('warning');

        $this->writer->remove('SwagTheme');

        static::assertFalse($this->privateFilesystem->directoryExists('snippets/administration/SwagTheme'));
    }

    public function testLocaleKeysCannotEscapeTheThemeDirectory(): void
    {
        $this->cacheInvalidator->expects($this->once())->method('invalidate');
        $this->logger->expects($this->once())->method('warning');

        $this->writer->write($this->createConfiguration([
            'sw-logo' => ['label' => ['../../../translation/escaped' => 'Escaped', 'en-GB' => 'Logo']],
        ]));

        $writtenPaths = $this->privateFilesystem->listContents('', true)
            ->filter(static fn (StorageAttributes $attributes): bool => $attributes->isFile())
            ->map(static fn (StorageAttributes $attributes): string => $attributes->path())
            ->toArray();

        static::assertSame(['snippets/administration/SwagTheme/en-GB.json'], $writtenPaths);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function createConfiguration(array $fields): StorefrontPluginConfiguration
    {
        $configuration = new StorefrontPluginConfiguration('SwagTheme');
        $configuration->setThemeJson(['name' => 'SwagTheme', 'config' => ['fields' => $fields]]);

        return $configuration;
    }

    /**
     * @return array<string, mixed>
     */
    private function readSnippetFile(string $path): array
    {
        return \json_decode($this->privateFilesystem->read($path), true, 512, \JSON_THROW_ON_ERROR);
    }
}
