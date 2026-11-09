<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductDocument;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentConfig;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentReader;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentValidator;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\OutcomeRow;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcher;
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
     * @var list<WriteCommand> the write's document commands
     */
    private array $documents = [];

    /**
     * @var list<OutcomeRow> what WriteOutcome returns for the translations
     */
    private array $translations = [];

    /**
     * @var array<string, array{extension: string|null, size: int|null, private: bool, path: string|null}>
     */
    private array $storedFiles = [];

    /**
     * @var array<string, int>
     */
    private array $storedDocuments = [];

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->registry = new StaticDefinitionInstanceRegistry(
            [ProductDocumentDefinition::class, ProductDocumentTranslationDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
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
        yield 'documents the same write deletes make room' => [10, [['deleted', '/0'], ['added', '/1']], []];
    }

    /**
     * @param list<array{string, string}> $changes the change and path of each document
     * @param list<string> $pointers
     */
    #[DataProvider('documentCountProvider')]
    public function testAProductCanHaveTenDocuments(int $storedDocuments, array $changes, array $pointers): void
    {
        $this->storedDocuments = [$this->ids->get('drill') . Defaults::LIVE_VERSION => $storedDocuments];
        foreach ($changes as $number => [$change, $path]) {
            $document = 'document-' . $number;
            $this->documents[] = match ($change) {
                'added' => $this->addedDocument($document, $path),
                'edited' => $this->editedDocument($document, $path),
                'deleted' => $this->deletedDocument($document),
                default => static::fail('Unknown change ' . $change),
            };
        }

        $expected = array_map(static fn (string $pointer): array => ['code' => ProductDocumentValidator::VIOLATION_TOO_MANY_DOCUMENTS, 'pointer' => $pointer], $pointers);
        static::assertSame($expected, $this->validate());
    }

    public function testAWriteThatAddsNoDocumentCountsNothing(): void
    {
        $writeOutcome = static::createStub(WriteOutcome::class);
        $writeOutcome->method('resultingRows')->willReturn([]);
        $reader = $this->createMock(ProductDocumentReader::class);
        $reader->expects($this->never())->method('countDocuments');

        $event = new PreWriteValidationEvent(WriteContext::createFromContext(Context::createDefaultContext()), [
            $this->deletedDocument('manual'),
            $this->editedDocument('datasheet', '/1'),
        ]);

        (new ProductDocumentValidator($writeOutcome, StaticEntityRepository::of(MediaCollection::class), $reader, new ProductDocumentConfig(new EventDispatcher(), ['pdf'], maxPerProduct: 10, maxFileSize: 1024)))
            ->validate($event);

        static::assertSame([], $this->errorsOf($event));
    }

    /**
     * @return array{extension: string|null, size: int|null, private: bool, path: string|null}
     */
    private static function file(?string $extension = 'pdf', ?int $size = 1024, bool $private = false, ?string $path = 'media/manual.pdf'): array
    {
        return ['extension' => $extension, 'size' => $size, 'private' => $private, 'path' => $path];
    }

    /**
     * @param list<WriteCommand> $commands
     *
     * @return list<array{code: mixed, pointer: mixed}>
     */
    private function validate(array $commands = []): array
    {
        // an Admin API request runs in the user scope, where the media repository hides private files
        $context = Context::createDefaultContext(new AdminApiSource(null));
        $event = new PreWriteValidationEvent(WriteContext::createFromContext($context), [...$this->documents, ...$commands]);

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

        $reader = static::createStub(ProductDocumentReader::class);
        // the stored documents, without those the write deletes
        $reader->method('countDocuments')->willReturnCallback(fn (array $products, array $excludedDocumentIds): array => array_map(
            static fn (int $count): int => $count - \count($excludedDocumentIds),
            array_intersect_key($this->storedDocuments, array_flip($products)),
        ));

        return new ProductDocumentValidator($writeOutcome, $mediaRepository, $reader, new ProductDocumentConfig(new EventDispatcher(), ['pdf', 'docx'], maxPerProduct: 10, maxFileSize: 1024));
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

    private function addedDocument(string $document, string $path): InsertCommand
    {
        return new InsertCommand(
            $this->registry->get(ProductDocumentDefinition::class),
            ['product_id' => $this->ids->getBytes('drill'), 'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            $this->documentKey($document),
            EntityExistence::createForEntity(ProductDocumentDefinition::ENTITY_NAME, []),
            $path,
        );
    }

    private function editedDocument(string $document, string $path): UpdateCommand
    {
        return new UpdateCommand(
            $this->registry->get(ProductDocumentDefinition::class),
            // a save through the product sends the product of every document
            ['position' => 1, 'product_id' => $this->ids->getBytes('drill'), 'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            $this->documentKey($document),
            EntityExistence::createForEntity(ProductDocumentDefinition::ENTITY_NAME, []),
            $path,
        );
    }

    private function deletedDocument(string $document): DeleteCommand
    {
        return new DeleteCommand(
            $this->registry->get(ProductDocumentDefinition::class),
            $this->documentKey($document),
            EntityExistence::createForEntity(ProductDocumentDefinition::ENTITY_NAME, []),
        );
    }

    /**
     * @return array<string, string>
     */
    private function documentKey(string $document): array
    {
        return ['id' => $this->ids->getBytes($document), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)];
    }
}
