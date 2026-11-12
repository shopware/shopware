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
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
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
     * @var list<OutcomeRow>
     */
    private array $documents = [];

    /**
     * @var list<OutcomeRow>
     */
    private array $translations = [];

    /**
     * @var list<OutcomeRow>
     */
    private array $writtenFiles = [];

    private MediaCollection $storedFiles;

    /**
     * @var list<string>
     */
    private array $linkedFileIds = [];

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->storedFiles = new MediaCollection();
    }

    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [PreWriteValidationEvent::class => 'validate'],
            ProductDocumentValidator::getSubscribedEvents()
        );
    }

    public static function fileProvider(): iterable
    {
        yield 'an allowed file' => [self::file(), []];
        yield 'an allowed file type in upper case' => [self::file(extension: 'PDF'), []];
        yield 'another file type' => [self::file(extension: 'xlsx'), [ProductDocumentValidator::VIOLATION_FILE_EXTENSION_NOT_ALLOWED]];
        yield 'a file over the maximum size' => [self::file(size: 1025), [ProductDocumentValidator::VIOLATION_FILE_TOO_LARGE]];
        yield 'a file of another type over the maximum size gets both errors' => [self::file(extension: 'xlsx', size: 1025), [ProductDocumentValidator::VIOLATION_FILE_EXTENSION_NOT_ALLOWED, ProductDocumentValidator::VIOLATION_FILE_TOO_LARGE]];
        yield 'a file without content' => [self::file(extension: null, size: null, path: null), [ProductDocumentValidator::VIOLATION_FILE_SIZE_UNKNOWN]];
        yield 'a link to a file on another server' => [self::file(path: 'https://cdn.example.com/manual.pdf'), [ProductDocumentValidator::VIOLATION_FILE_SIZE_UNKNOWN]];
        yield 'a private file only gets the private error' => [self::file(extension: 'xlsx', size: 1025, private: true), [ProductDocumentValidator::VIOLATION_FILE_PRIVATE]];
    }

    /**
     * @param list<string> $codes
     */
    #[DataProvider('fileProvider')]
    public function testTheFileMustFitTheLimits(MediaEntity $file, array $codes): void
    {
        $this->storeFile($file);
        $this->translations = [$this->translation('manual', self::GERMAN, '/0/translations/' . self::GERMAN, file: 'manual-file')];

        $expected = array_map(static fn (string $code): array => ['code' => $code, 'pointer' => '/0/translations/' . self::GERMAN . '/mediaId'], $codes);
        static::assertSame($expected, $this->validate());
    }

    public function testAFileTheSearchDoesNotReturnIsReportedAsNotFound(): void
    {
        $this->translations = [$this->translation('manual', Defaults::LANGUAGE_SYSTEM, '/0', file: 'missing-file')];

        static::assertSame([['code' => ProductDocumentValidator::VIOLATION_FILE_NOT_FOUND, 'pointer' => '/0/mediaId']], $this->validate());
    }

    public function testAFileIsOnlyCheckedWhenATranslationGetsIt(): void
    {
        // a file that broke a limit later doesn't block writes that keep it
        $this->storeFile(self::file(private: true));
        $this->translations = [$this->translation('manual', Defaults::LANGUAGE_SYSTEM, '/0', file: 'manual-file', storedFile: 'manual-file')];

        static::assertSame([], $this->validate());
    }

    public function testAWriteWithoutDocumentTranslationsPasses(): void
    {
        $this->translations = [];

        static::assertSame([], $this->validate());
    }

    public static function copyScopeProvider(): iterable
    {
        yield 'a clone or a new version copies the files as they are' => [VersionManager::CLONE_SCOPE];
        yield 'a merged version copies the files as they are' => [VersionManager::MERGE_SCOPE];
    }

    #[DataProvider('copyScopeProvider')]
    public function testCopiedDocumentsAreNotChecked(string $state): void
    {
        $this->storeFile(self::file(extension: 'xlsx', size: 1025));
        $this->documents = [$this->document('manual', '/0')];
        $this->translations = [$this->translation('manual', self::GERMAN, '/0/translations/' . self::GERMAN, file: 'manual-file')];

        static::assertSame([], $this->validate($state));
    }

    public function testANewDocumentNeedsAFileInTheDefaultLanguage(): void
    {
        $this->storeFile(self::file());
        $this->documents = [$this->document('manual', '/0')];
        $this->translations = [$this->translation('manual', self::GERMAN, '/0/translations/' . self::GERMAN, file: 'manual-file')];

        static::assertSame([
            ['code' => ProductDocumentValidator::VIOLATION_DEFAULT_LANGUAGE_FILE_MISSING, 'pointer' => '/0/translations/' . Defaults::LANGUAGE_SYSTEM . '/mediaId'],
        ], $this->validate());
    }

    public function testANewDocumentWithAFileInTheDefaultLanguagePasses(): void
    {
        $this->storeFile(self::file());
        $this->documents = [$this->document('manual', '/0')];
        $this->translations = [$this->translation('manual', Defaults::LANGUAGE_SYSTEM, '/0', file: 'manual-file')];

        static::assertSame([], $this->validate());
    }

    public function testTheDefaultLanguageCannotLoseItsFile(): void
    {
        $this->translations = [$this->translation('manual', Defaults::LANGUAGE_SYSTEM, '/0', file: null, storedFile: 'manual-file')];

        static::assertSame([
            ['code' => ProductDocumentValidator::VIOLATION_DEFAULT_LANGUAGE_FILE_MISSING, 'pointer' => '/0/mediaId'],
        ], $this->validate());
    }

    public function testAnotherLanguageWithoutAFileFallsBackToTheDefaultLanguage(): void
    {
        $this->translations = [$this->translation('manual', self::GERMAN, '/0', file: null)];

        static::assertSame([], $this->validate());
    }

    public function testAFileWrittenInTheSameWriteIsCheckedWithItsNewValues(): void
    {
        $this->writtenFiles = [$this->writtenFile('/0', stored: ['file_extension' => 'pdf'], written: ['file_extension' => 'zip'])];
        $this->translations = [$this->translation('manual', Defaults::LANGUAGE_SYSTEM, '/1', file: 'manual-file')];

        static::assertSame([
            ['code' => ProductDocumentValidator::VIOLATION_FILE_EXTENSION_NOT_ALLOWED, 'pointer' => '/1/mediaId'],
        ], $this->validate());
    }

    public function testAFileCreatedInTheSameWriteIsChecked(): void
    {
        $this->writtenFiles = [$this->writtenFile('/0', stored: null, written: ['file_extension' => 'pdf', 'file_size' => 2048, 'path' => 'media/manual.pdf'])];
        $this->translations = [$this->translation('manual', Defaults::LANGUAGE_SYSTEM, '/1', file: 'manual-file')];

        static::assertSame([
            ['code' => ProductDocumentValidator::VIOLATION_FILE_TOO_LARGE, 'pointer' => '/1/mediaId'],
        ], $this->validate());
    }

    public static function linkedFileChangeProvider(): iterable
    {
        yield 'a new file of another type' => [['file_extension' => 'zip'], ProductDocumentValidator::VIOLATION_FILE_EXTENSION_NOT_ALLOWED];
        yield 'a new file over the maximum size' => [['file_size' => 1025], ProductDocumentValidator::VIOLATION_FILE_TOO_LARGE];
        yield 'a file made private' => [['private' => 1], ProductDocumentValidator::VIOLATION_FILE_PRIVATE];
        yield 'a file moved to another server' => [['path' => 'https://cdn.example.com/manual.pdf'], ProductDocumentValidator::VIOLATION_FILE_SIZE_UNKNOWN];
    }

    /**
     * @param array<string, mixed> $written
     */
    #[DataProvider('linkedFileChangeProvider')]
    public function testChangingTheFileOfADocumentIsChecked(array $written, string $code): void
    {
        $this->writtenFiles = [$this->writtenFile('/0', stored: [], written: $written)];
        $this->linkedFileIds = [$this->ids->get('manual-file')];

        static::assertSame([['code' => $code, 'pointer' => '/0']], $this->validate());
    }

    public function testChangingAFileNoDocumentUsesPasses(): void
    {
        $this->writtenFiles = [$this->writtenFile('/0', stored: [], written: ['file_extension' => 'zip'])];
        $this->linkedFileIds = [];

        static::assertSame([], $this->validate());
    }

    public function testWritingAFileWithoutChangingItIsNotChecked(): void
    {
        $this->writtenFiles = [$this->writtenFile('/0', stored: ['file_extension' => 'zip'], written: ['file_extension' => 'zip'])];
        $this->linkedFileIds = [$this->ids->get('manual-file')];

        static::assertSame([], $this->validate());
    }

    private static function file(?string $extension = 'pdf', ?int $size = 1024, bool $private = false, ?string $path = 'media/manual.pdf'): MediaEntity
    {
        $file = new MediaEntity();
        $file->setPrivate($private);
        $file->setPath($path);
        if ($extension !== null) {
            $file->setFileExtension($extension);
        }
        if ($size !== null) {
            $file->setFileSize($size);
        }

        return $file;
    }

    private function storeFile(MediaEntity $file): void
    {
        $file->setId($this->ids->get('manual-file'));
        $this->storedFiles->add($file);
    }

    /**
     * @return list<array{code: mixed, pointer: mixed}>
     */
    private function validate(string ...$states): array
    {
        // an Admin API request runs in the user scope, where the media repository hides private files
        $context = Context::createDefaultContext(new AdminApiSource(null));
        $writeContext = WriteContext::createFromContext($context);
        $writeContext->addState(...$states);
        $event = new PreWriteValidationEvent($writeContext, []);

        $this->validator()->validate($event);

        $errors = [];
        foreach ($event->getExceptions()->getErrors() as $error) {
            $errors[] = ['code' => $error['code'], 'pointer' => $error['source']['pointer'] ?? null];
        }

        return $errors;
    }

    private function validator(): ProductDocumentValidator
    {
        $writeOutcome = static::createStub(WriteOutcome::class);
        $writeOutcome->method('resultingRows')->willReturnCallback(fn (array $commands, array $columns): array => match ($columns) {
            ['product_id'] => $this->documents,
            ['media_id'] => $this->translations,
            default => $this->writtenFiles,
        });

        $search = function (Criteria $criteria, Context $context): MediaCollection|array {
            if ($criteria->getTitle() === 'product-document-validator::linked-files') {
                static::assertSame(Context::SYSTEM_SCOPE, $context->getScope());

                return array_values(array_intersect($this->linkedFileIds, $criteria->getIds()));
            }

            // the caller's scope, so files the caller can't see stay hidden
            static::assertSame(Context::USER_SCOPE, $context->getScope());

            return $this->storedFiles->filter(static fn (MediaEntity $file): bool => \in_array($file->getId(), $criteria->getIds(), true));
        };
        $mediaRepository = StaticEntityRepository::of(MediaCollection::class, [$search, $search]);

        return new ProductDocumentValidator($writeOutcome, $mediaRepository, new ProductDocumentConfig(new EventDispatcher(), ['pdf', 'docx'], maxFileSize: 1024));
    }

    private function translation(string $document, string $languageId, string $path, ?string $file, ?string $storedFile = null): OutcomeRow
    {
        return new OutcomeRow(
            primaryKey: [
                'product_document_id' => $this->ids->getBytes($document),
                'product_document_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'language_id' => Uuid::fromHexToBytes($languageId),
            ],
            path: $path,
            isNew: $storedFile === null,
            isDeleted: false,
            payload: ['media_id' => $file === null ? null : $this->ids->getBytes($file)],
            state: ['media_id' => $storedFile === null ? null : $this->ids->getBytes($storedFile)],
        );
    }

    private function document(string $document, string $path): OutcomeRow
    {
        return new OutcomeRow(
            primaryKey: [
                'id' => $this->ids->getBytes($document),
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            path: $path,
            isNew: true,
            isDeleted: false,
            payload: ['product_id' => $this->ids->getBytes('drill')],
            state: ['product_id' => null],
        );
    }

    /**
     * @param array<string, mixed>|null $stored
     * @param array<string, mixed> $written
     */
    private function writtenFile(string $path, ?array $stored, array $written): OutcomeRow
    {
        $state = array_fill_keys(['private', 'path', 'file_extension', 'file_size'], null);
        if ($stored !== null) {
            $state = [...$state, 'private' => 0, 'path' => 'media/manual.pdf', 'file_extension' => 'pdf', 'file_size' => 1024, ...$stored];
        }

        return new OutcomeRow(
            primaryKey: ['id' => $this->ids->getBytes('manual-file')],
            path: $path,
            isNew: $stored === null,
            isDeleted: false,
            payload: $written,
            state: $state,
        );
    }
}
