<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductDocument;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentLimits;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentReader;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentValidator;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\OutcomeRow;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentValidator::class)]
class ProductDocumentValidatorTest extends TestCase
{
    private const GERMAN = 'f0e1d2c3b4a5968778695a4b3c2d1e0f';

    private IdsCollection $ids;

    private StaticDefinitionInstanceRegistry $registry;

    /**
     * The documents the write touches, as WriteOutcome returns them.
     *
     * @var list<OutcomeRow>
     */
    private array $documents = [];

    /**
     * The translations the write gives a file, as WriteOutcome returns them.
     *
     * @var list<OutcomeRow>
     */
    private array $translations = [];

    /**
     * The files the write creates or changes, as WriteOutcome returns them.
     *
     * @var list<OutcomeRow>
     */
    private array $media = [];

    /**
     * @var array<string, array{extension: string|null, size: int|null, private: bool}>
     */
    private array $storedFiles = [];

    /**
     * @var array<string, int>
     */
    private array $storedDocuments = [];

    /**
     * @var list<array{document: string, languageId: string, mediaId: string}>
     */
    private array $sharedFiles = [];

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->registry = new StaticDefinitionInstanceRegistry(
            [ProductDocumentDefinition::class, ProductDocumentTranslationDefinition::class, LanguageDefinition::class, MediaDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
    }

    /**
     * @return iterable<string, array{array{extension: string|null, size: int|null, private: bool}, list<string>}>
     */
    public static function fileProvider(): iterable
    {
        yield 'an allowed file' => [['extension' => 'pdf', 'size' => 1024, 'private' => false], []];
        yield 'an allowed file type in upper case' => [['extension' => 'PDF', 'size' => 1024, 'private' => false], []];
        yield 'another file type' => [['extension' => 'xlsx', 'size' => 1024, 'private' => false], [ProductDocumentValidator::VIOLATION_FILE_EXTENSION_NOT_ALLOWED]];
        yield 'a file over the maximum size' => [['extension' => 'pdf', 'size' => 1025, 'private' => false], [ProductDocumentValidator::VIOLATION_FILE_TOO_LARGE]];
        yield 'a file without content' => [['extension' => null, 'size' => null, 'private' => false], [ProductDocumentValidator::VIOLATION_FILE_SIZE_UNKNOWN]];
        yield 'a private file' => [['extension' => 'pdf', 'size' => 1024, 'private' => true], [ProductDocumentValidator::VIOLATION_FILE_PRIVATE]];
    }

    /**
     * @param array{extension: string|null, size: int|null, private: bool} $file
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

    public function testAFileTheSameWriteCreatesIsCheckedAsItWillBe(): void
    {
        $this->media = [new OutcomeRow(
            primaryKey: ['id' => $this->ids->getBytes('manual-file')],
            path: '/0',
            isNew: true,
            isDeleted: false,
            payload: ['file_name' => 'manual'],
            state: ['file_extension' => null, 'file_size' => null, 'private' => null],
        )];
        $this->translations = [$this->translation('manual', Defaults::LANGUAGE_SYSTEM, '/1/translations/' . Defaults::LANGUAGE_SYSTEM, file: 'manual-file')];

        $errors = $this->validate([$this->mediaCommand('manual-file', '/0')]);

        static::assertSame([['code' => ProductDocumentValidator::VIOLATION_FILE_SIZE_UNKNOWN, 'pointer' => '/1/translations/' . Defaults::LANGUAGE_SYSTEM . '/mediaId']], $errors);
    }

    public function testAFileIsOnlyCheckedWhenATranslationGetsIt(): void
    {
        // the file broke a limit after it was added, which doesn't stop writes that keep it
        $this->storedFiles = [$this->ids->get('manual-file') => ['extension' => 'pdf', 'size' => 1024, 'private' => true]];
        $this->translations = [$this->translation('manual', Defaults::LANGUAGE_SYSTEM, '/0', file: 'manual-file', storedFile: 'manual-file')];

        static::assertSame([], $this->validate());
    }

    public function testANewDocumentNeedsAFileInTheDefaultLanguage(): void
    {
        $this->storedFiles = [
            $this->ids->get('manual-file') => ['extension' => 'pdf', 'size' => 1024, 'private' => false],
            $this->ids->get('zertifikat-file') => ['extension' => 'pdf', 'size' => 1024, 'private' => false],
        ];
        $this->documents = [
            $this->addedDocument('manual', '/0/documents/0'),
            $this->addedDocument('certificate', '/0/documents/1'),
            // an existing document can't lose its default-language file
            $this->editedDocument('datasheet', '/0/documents/2'),
        ];
        $this->translations = [
            $this->translation('manual', Defaults::LANGUAGE_SYSTEM, '/0/documents/0/translations/' . Defaults::LANGUAGE_SYSTEM, file: 'manual-file'),
            $this->translation('certificate', self::GERMAN, '/0/documents/1/translations/' . self::GERMAN, file: 'zertifikat-file'),
        ];

        static::assertSame([['code' => ProductDocumentValidator::VIOLATION_DEFAULT_TRANSLATION_MISSING, 'pointer' => '/0/documents/1']], $this->validate());
    }

    /**
     * @return iterable<string, array{int, list<array{string, string}>, list<string>}>
     */
    public static function documentCountProvider(): iterable
    {
        yield 'the documents added last are the ones that do not fit' => [
            9,
            [['edited', '/0/documents/0'], ['added', '/0/documents/1'], ['added', '/0/documents/2']],
            ['/0/documents/2'],
        ];
        yield 'edits are allowed over a lowered limit' => [12, [['edited', '/0/documents/0']], []];
        yield 'a document moved from another product is added' => [10, [['moved in', '/0']], ['/0']];
        yield 'deleted documents make room' => [10, [['deleted', '/0'], ['added', '/1']], []];
        yield 'documents moved to another product make room' => [10, [['moved out', '/0'], ['added', '/1']], []];
    }

    /**
     * @param list<array{string, string}> $changes what the write does to which document
     * @param list<string> $pointers
     */
    #[DataProvider('documentCountProvider')]
    public function testAProductCanHaveTenDocumentsPerLanguage(int $storedDocuments, array $changes, array $pointers): void
    {
        $this->storedDocuments = [$this->ids->get('drill') . Defaults::LIVE_VERSION => $storedDocuments];
        $this->storedFiles = [$this->ids->get('file') => ['extension' => 'pdf', 'size' => 1024, 'private' => false]];
        foreach ($changes as $number => [$change, $path]) {
            $document = 'document-' . $number;
            if ($change === 'added') {
                $this->translations[] = $this->translation($document, Defaults::LANGUAGE_SYSTEM, $path . '/translations/' . Defaults::LANGUAGE_SYSTEM, file: 'file');
            }

            $this->documents[] = match ($change) {
                'added' => $this->addedDocument($document, $path),
                'edited' => $this->editedDocument($document, $path),
                'moved in' => $this->editedDocument($document, $path, product: 'screwdriver', movedTo: 'drill'),
                'moved out' => $this->editedDocument($document, $path, movedTo: 'screwdriver'),
                'deleted' => $this->deletedDocument($document, $path),
                default => static::fail('Unknown change ' . $change),
            };
        }

        $expected = array_map(static fn (string $pointer): array => ['code' => ProductDocumentValidator::VIOLATION_TOO_MANY_DOCUMENTS, 'pointer' => $pointer], $pointers);
        static::assertSame($expected, $this->validate());
    }

    public function testAFileCanBeADocumentOfAProductOnlyOnce(): void
    {
        $manual = $this->ids->get('manual') . Defaults::LIVE_VERSION;
        $this->sharedFiles = [
            ['document' => $manual, 'languageId' => Defaults::LANGUAGE_SYSTEM, 'mediaId' => $this->ids->get('manual-file')],
            // shared before this write, which only changes the title
            ['document' => $manual, 'languageId' => self::GERMAN, 'mediaId' => $this->ids->get('handbuch-file')],
        ];

        $translationDefinition = $this->registry->get(ProductDocumentTranslationDefinition::class);
        $event = new PostWriteValidationEvent(WriteContext::createFromContext(Context::createDefaultContext()), [
            new InsertCommand(
                $translationDefinition,
                ['title' => 'Manual', 'media_id' => $this->ids->getBytes('manual-file')],
                $this->translationKey('manual', Defaults::LANGUAGE_SYSTEM),
                EntityExistence::createForEntity(ProductDocumentTranslationDefinition::ENTITY_NAME, []),
                '/0/translations/' . Defaults::LANGUAGE_SYSTEM,
            ),
            new UpdateCommand(
                $translationDefinition,
                ['title' => 'Handbuch'],
                $this->translationKey('manual', self::GERMAN),
                EntityExistence::createForEntity(ProductDocumentTranslationDefinition::ENTITY_NAME, []),
                '/0/translations/' . self::GERMAN,
            ),
        ]);

        $this->validator()->validateUniqueFiles($event);

        static::assertSame(
            [['code' => ProductDocumentValidator::VIOLATION_FILE_ALREADY_USED, 'pointer' => '/0/translations/' . Defaults::LANGUAGE_SYSTEM . '/mediaId']],
            $this->errorsOf($event),
        );
    }

    /**
     * @param list<WriteCommand> $commands
     *
     * @return list<array{code: mixed, pointer: mixed}>
     */
    private function validate(array $commands = []): array
    {
        $event = new PreWriteValidationEvent(WriteContext::createFromContext(Context::createDefaultContext()), $commands);

        $this->validator()->validate($event);

        return $this->errorsOf($event);
    }

    private function validator(): ProductDocumentValidator
    {
        $writeOutcome = static::createStub(WriteOutcome::class);
        $writeOutcome->method('touchedRows')->willReturnCallback(fn (): array => $this->documents);
        $writeOutcome->method('resultingRows')->willReturnCallback(
            fn (array $commands, array $columns): array => $columns === ['media_id'] ? $this->translations : $this->media,
        );

        $reader = static::createStub(ProductDocumentReader::class);
        $reader->method('getFiles')->willReturnCallback(fn (array $mediaIds): array => array_intersect_key($this->storedFiles, array_flip($mediaIds)));
        $reader->method('countDocuments')->willReturnCallback(fn (array $products): array => array_intersect_key($this->storedDocuments, array_flip($products)));
        $reader->method('getSharedFiles')->willReturnCallback(fn (): array => $this->sharedFiles);

        return new ProductDocumentValidator($writeOutcome, $reader, new ProductDocumentLimits(['pdf', 'docx'], maxPerLanguage: 10, maxFileSize: 1024));
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

    private function addedDocument(string $document, string $path): OutcomeRow
    {
        return new OutcomeRow(
            primaryKey: $this->documentKey($document),
            path: $path,
            isNew: true,
            isDeleted: false,
            payload: ['product_id' => $this->ids->getBytes('drill'), 'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            state: ['product_id' => null, 'product_version_id' => null],
        );
    }

    private function editedDocument(string $document, string $path, string $product = 'drill', ?string $movedTo = null): OutcomeRow
    {
        return new OutcomeRow(
            primaryKey: $this->documentKey($document),
            path: $path,
            isNew: false,
            isDeleted: false,
            // a write through the product sets the product of each of its documents, moved or not
            payload: ['product_id' => $this->ids->getBytes($movedTo ?? $product), 'position' => 1],
            state: ['product_id' => $this->ids->getBytes($product), 'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
        );
    }

    private function deletedDocument(string $document, string $path): OutcomeRow
    {
        return new OutcomeRow(
            primaryKey: $this->documentKey($document),
            path: $path,
            isNew: false,
            isDeleted: true,
            payload: [],
            state: ['product_id' => $this->ids->getBytes('drill'), 'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
        );
    }

    /**
     * @return array<string, string>
     */
    private function documentKey(string $document): array
    {
        return ['id' => $this->ids->getBytes($document), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)];
    }

    private function mediaCommand(string $media, string $path): InsertCommand
    {
        return new InsertCommand(
            $this->registry->get(MediaDefinition::class),
            ['id' => $this->ids->getBytes($media), 'file_name' => $media],
            ['id' => $this->ids->getBytes($media)],
            EntityExistence::createForEntity(MediaDefinition::ENTITY_NAME, ['id' => $this->ids->get($media)]),
            $path,
        );
    }
}
