<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Service;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Service\AbstractTranslationLoader;
use Shopware\Core\System\Snippet\Service\TranslationMetadataStore;
use Shopware\Core\System\Snippet\Service\TranslationRemover;
use Shopware\Core\System\Snippet\SnippetException;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TranslationRemover::class)]
class TranslationRemoverTest extends TestCase
{
    private Filesystem $filesystem;

    private AbstractTranslationLoader&MockObject $translationLoader;

    private TranslationMetadataStore&MockObject $metadataStore;

    private TranslationRemover $remover;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->translationLoader = $this->createMock(AbstractTranslationLoader::class);
        $this->metadataStore = $this->createMock(TranslationMetadataStore::class);

        $this->remover = new TranslationRemover($this->filesystem, $this->translationLoader, $this->metadataStore);
    }

    public function testRemoveDeletesFilesAndMetadata(): void
    {
        $path = 'translation/locale/es-ES';
        $this->translationLoader->method('getLocalePath')->with('es-ES')->willReturn($path);
        $this->filesystem->write($path . '/Platform/messages.es-ES.base.json', '{}');
        static::assertTrue($this->filesystem->directoryExists($path));

        $this->metadataStore->expects($this->once())->method('remove')->with('es-ES');

        $this->remover->remove('es-ES');

        static::assertFalse($this->filesystem->directoryExists($path));
    }

    public function testRemoveStillDropsMetadataWhenNoFilesExist(): void
    {
        $this->translationLoader->method('getLocalePath')->with('es-ES')->willReturn('translation/locale/es-ES');
        $this->metadataStore->expects($this->once())->method('remove')->with('es-ES');

        $this->remover->remove('es-ES');

        static::assertFalse($this->filesystem->directoryExists('translation/locale/es-ES'));
    }

    public function testRemoveThrowsForInvalidLocale(): void
    {
        $this->translationLoader->method('getLocalePath')->willReturn('');
        $this->metadataStore->expects($this->never())->method('remove');

        $this->expectExceptionObject(SnippetException::localeDoesNotExist('_invalid_'));

        $this->remover->remove('_invalid_');
    }
}
