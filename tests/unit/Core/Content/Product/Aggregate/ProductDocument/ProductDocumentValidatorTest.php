<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductDocument;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentConfig;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentValidator;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\OutcomeRow;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentValidator::class)]
class ProductDocumentValidatorTest extends TestCase
{
    private const GERMAN = 'f0e1d2c3b4a5968778695a4b3c2d1e0f';

    private IdsCollection $ids;

    /**
     * @var list<OutcomeRow> what WriteOutcome returns for the translations
     */
    private array $translations = [];

    /**
     * @var array<string, array{extension: string|null, size: int|null, private: bool, path: string|null}>
     */
    private array $storedFiles = [];

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    /**
     * @return iterable<string, array{array{extension: string|null, size: int|null, private: bool, path: string|null}, list<string>}>
     */
    public static function fileProvider(): iterable
    {
        yield 'an allowed file' => [self::file(), []];
        yield 'an allowed file type in upper case' => [self::file(extension: 'PDF'), []];
        yield 'another file type' => [self::file(extension: 'xlsx'), [ProductDocumentValidator::VIOLATION_FILE_EXTENSION_NOT_ALLOWED]];
        yield 'a file over the maximum size' => [self::file(size: 1025), [ProductDocumentValidator::VIOLATION_FILE_TOO_LARGE]];
        yield 'a file without content' => [self::file(extension: null, size: null, path: null), [ProductDocumentValidator::VIOLATION_FILE_SIZE_UNKNOWN]];
        yield 'a link to a file on another server' => [self::file(path: 'https://cdn.example.com/manual.pdf'), [ProductDocumentValidator::VIOLATION_FILE_SIZE_UNKNOWN]];
        yield 'a private file' => [self::file(private: true), [ProductDocumentValidator::VIOLATION_FILE_PRIVATE]];
    }

    /**
     * @param array{extension: string|null, size: int|null, private: bool, path: string|null} $file
     * @param list<string> $codes
     */
    #[DataProvider('fileProvider')]
    public function testTheFileMustFitTheLimits(array $file, array $codes): void
    {
        $this->storedFiles = [$this->ids->get('manual-file') => $file];
        $this->translations = [$this->translation('manual', self::GERMAN, '/0/translations/' . self::GERMAN, file: 'manual-file')];

        $expected = array_map(static fn (string $code): array => ['code' => $code, 'pointer' => '/0/translations/' . self::GERMAN . '/mediaId'], $codes);
        static::assertSame($expected, $this->validate());
    }

    public function testAFileThatDoesNotExistIsLeftToTheDatabase(): void
    {
        $this->translations = [$this->translation('manual', Defaults::LANGUAGE_SYSTEM, '/0', file: 'missing-file')];

        static::assertSame([], $this->validate());
    }

    public function testAFileIsOnlyCheckedWhenATranslationGetsIt(): void
    {
        // a file that broke a limit later doesn't block writes that keep it
        $this->storedFiles = [$this->ids->get('manual-file') => self::file(private: true)];
        $this->translations = [$this->translation('manual', Defaults::LANGUAGE_SYSTEM, '/0', file: 'manual-file', storedFile: 'manual-file')];

        static::assertSame([], $this->validate());
    }

    /**
     * @return array{extension: string|null, size: int|null, private: bool, path: string|null}
     */
    private static function file(?string $extension = 'pdf', ?int $size = 1024, bool $private = false, ?string $path = 'media/manual.pdf'): array
    {
        return ['extension' => $extension, 'size' => $size, 'private' => $private, 'path' => $path];
    }

    /**
     * @return list<array{code: mixed, pointer: mixed}>
     */
    private function validate(): array
    {
        // an Admin API request runs in the user scope, where the media repository hides private files
        $context = Context::createDefaultContext(new AdminApiSource(null));
        $event = new PreWriteValidationEvent(WriteContext::createFromContext($context), []);

        $this->validator()->validate($event);

        return $this->errorsOf($event);
    }

    private function validator(): ProductDocumentValidator
    {
        $writeOutcome = static::createStub(WriteOutcome::class);
        $writeOutcome->method('resultingRows')->willReturnCallback(fn (): array => $this->translations);

        $mediaRepository = StaticEntityRepository::of(MediaCollection::class, [
            function (Criteria $criteria, Context $context): MediaCollection {
                // private files have to stay visible for the private check
                static::assertSame(Context::SYSTEM_SCOPE, $context->getScope());

                return $this->storedMedia($criteria->getIds());
            },
        ]);

        return new ProductDocumentValidator($writeOutcome, $mediaRepository, new ProductDocumentConfig(new EventDispatcher(), ['pdf', 'docx'], maxFileSize: 1024));
    }

    /**
     * @param array<string|array<string, string>> $mediaIds
     */
    private function storedMedia(array $mediaIds): MediaCollection
    {
        $media = new MediaCollection();
        foreach (array_intersect_key($this->storedFiles, array_flip(array_filter($mediaIds, \is_string(...)))) as $mediaId => $file) {
            $entity = new MediaEntity();
            $entity->setId($mediaId);
            $entity->setPrivate($file['private']);
            $entity->setPath($file['path']);
            if ($file['extension'] !== null) {
                $entity->setFileExtension($file['extension']);
            }
            if ($file['size'] !== null) {
                $entity->setFileSize($file['size']);
            }
            $media->add($entity);
        }

        return $media;
    }

    /**
     * @return list<array{code: mixed, pointer: mixed}>
     */
    private function errorsOf(PreWriteValidationEvent $event): array
    {
        $errors = [];
        foreach ($event->getExceptions()->getErrors() as $error) {
            $errors[] = ['code' => $error['code'], 'pointer' => $error['source']['pointer'] ?? null];
        }

        return $errors;
    }

    private function translation(string $document, string $languageId, string $path, string $file, ?string $storedFile = null): OutcomeRow
    {
        return new OutcomeRow(
            primaryKey: $this->translationKey($document, $languageId),
            path: $path,
            isNew: $storedFile === null,
            isDeleted: false,
            payload: ['media_id' => $this->ids->getBytes($file)],
            state: ['media_id' => $storedFile === null ? null : $this->ids->getBytes($storedFile)],
        );
    }

    /**
     * @return array<string, string>
     */
    private function translationKey(string $document, string $languageId): array
    {
        return [
            'product_document_id' => $this->ids->getBytes($document),
            'product_document_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'language_id' => Uuid::fromHexToBytes($languageId),
        ];
    }
}
