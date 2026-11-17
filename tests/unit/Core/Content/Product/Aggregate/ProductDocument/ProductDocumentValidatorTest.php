<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductDocument;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentConfig;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentValidator;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
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

    /**
     * @var list<OutcomeRow>
     */
    private array $translations = [];

    private MediaCollection $storedFiles;

    /**
     * @var list<WriteCommand>
     */
    private array $commands = [];

    /**
     * @var list<array{documentId: string, languageId: string}>
     */
    private array $storedDocumentFiles = [];

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

    public function testANewDocumentWithoutAFileIsRejected(): void
    {
        $this->commands = [
            $this->documentCommand(InsertCommand::class, '/0'),
            $this->translationCommand(InsertCommand::class, self::GERMAN, ['title' => 'Handbuch'], '/0/translations/' . self::GERMAN),
        ];

        static::assertSame([['code' => ProductDocumentValidator::VIOLATION_FILE_MISSING, 'pointer' => '/0/translations']], $this->validate());
    }

    public function testANewDocumentWithAFileInAnotherLanguagePasses(): void
    {
        $this->storeFile(self::file());
        $this->commands = [
            $this->documentCommand(InsertCommand::class, '/0'),
            $this->translationCommand(InsertCommand::class, self::GERMAN, ['media_id' => $this->ids->getBytes('manual-file')], '/0/translations/' . self::GERMAN),
        ];
        $this->translations = [$this->translation('manual', self::GERMAN, '/0/translations/' . self::GERMAN, file: 'manual-file')];

        static::assertSame([], $this->validate());
    }

    public function testRemovingTheLastFileOfADocumentIsRejected(): void
    {
        $this->commands = [$this->translationCommand(UpdateCommand::class, self::GERMAN, ['media_id' => null], '/0/documents/0/translations/' . self::GERMAN)];
        $this->translations = [$this->translation('manual', self::GERMAN, '/0/documents/0/translations/' . self::GERMAN, file: null, storedFile: 'manual-file')];
        $this->storedDocumentFiles = [['documentId' => $this->ids->get('manual'), 'languageId' => self::GERMAN]];

        static::assertSame([['code' => ProductDocumentValidator::VIOLATION_FILE_MISSING, 'pointer' => '/0/documents/0/translations']], $this->validate());
    }

    public function testRemovingAFileKeepsTheDocumentWhenAnotherLanguageHasOne(): void
    {
        $this->commands = [$this->translationCommand(UpdateCommand::class, self::GERMAN, ['media_id' => null], '/0/translations/' . self::GERMAN)];
        $this->translations = [$this->translation('manual', self::GERMAN, '/0/translations/' . self::GERMAN, file: null, storedFile: 'manual-file')];
        $this->storedDocumentFiles = [
            ['documentId' => $this->ids->get('manual'), 'languageId' => self::GERMAN],
            ['documentId' => $this->ids->get('manual'), 'languageId' => Defaults::LANGUAGE_SYSTEM],
        ];

        static::assertSame([], $this->validate());
    }

    public function testTheSystemLanguageFileIsLeftToTheRequiredFlag(): void
    {
        $this->commands = [
            $this->documentCommand(InsertCommand::class, '/0'),
            $this->translationCommand(InsertCommand::class, Defaults::LANGUAGE_SYSTEM, ['title' => 'Manual'], '/0/translations/' . Defaults::LANGUAGE_SYSTEM),
        ];

        static::assertSame([], $this->validate());
    }

    public function testADeletedDocumentIsNotChecked(): void
    {
        $this->commands = [
            $this->documentCommand(DeleteCommand::class, ''),
            $this->translationCommand(DeleteCommand::class, self::GERMAN, [], ''),
        ];

        static::assertSame([], $this->validate());
    }

    /**
     * @param class-string<InsertCommand|DeleteCommand> $class
     */
    private function documentCommand(string $class, string $path): WriteCommand
    {
        $primaryKey = ['id' => $this->ids->getBytes('manual'), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)];

        $definition = $this->definition(ProductDocumentDefinition::class);
        if ($class === DeleteCommand::class) {
            return new DeleteCommand($definition, $primaryKey, static::createStub(EntityExistence::class));
        }

        return new InsertCommand($definition, [], $primaryKey, static::createStub(EntityExistence::class), $path);
    }

    /**
     * @param class-string<InsertCommand|UpdateCommand|DeleteCommand> $class
     * @param array<string, mixed> $payload
     */
    private function translationCommand(string $class, string $languageId, array $payload, string $path): WriteCommand
    {
        $primaryKey = [
            'product_document_id' => $this->ids->getBytes('manual'),
            'product_document_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'language_id' => Uuid::fromHexToBytes($languageId),
        ];

        $definition = $this->definition(ProductDocumentTranslationDefinition::class);
        if ($class === DeleteCommand::class) {
            return new DeleteCommand($definition, $primaryKey, static::createStub(EntityExistence::class));
        }
        if ($class === UpdateCommand::class) {
            return new UpdateCommand($definition, $payload, $primaryKey, static::createStub(EntityExistence::class), $path);
        }

        return new InsertCommand($definition, $payload, $primaryKey, static::createStub(EntityExistence::class), $path);
    }

    /**
     * @param class-string<EntityDefinition> $class
     */
    private function definition(string $class): EntityDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [ProductDocumentDefinition::class, ProductDocumentTranslationDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        return $registry->get($class);
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
    private function validate(): array
    {
        // an Admin API request runs in the user scope, where the media repository hides private files
        $context = Context::createDefaultContext(new AdminApiSource(null));
        $event = new PreWriteValidationEvent(WriteContext::createFromContext($context), $this->commands);

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
        $writeOutcome->method('resultingRows')->willReturnCallback(fn (): array => $this->translations);

        $mediaRepository = StaticEntityRepository::of(MediaCollection::class, [
            function (Criteria $criteria, Context $context): MediaCollection {
                // the caller's scope, so files the caller can't see stay hidden
                static::assertSame(Context::USER_SCOPE, $context->getScope());

                return $this->storedFiles->filter(static fn (MediaEntity $file): bool => \in_array($file->getId(), $criteria->getIds(), true));
            },
        ]);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnCallback(fn (): array => $this->storedDocumentFiles);

        return new ProductDocumentValidator($writeOutcome, $mediaRepository, new ProductDocumentConfig(new EventDispatcher(), ['pdf', 'docx'], maxFileSize: 1024), $connection);
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
}
