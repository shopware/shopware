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
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
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

    private MediaCollection $files;

    /**
     * @var array<string, int> stored documents per product key
     */
    private array $storedDocuments = [];

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->files = new MediaCollection();
        $this->registry = new StaticDefinitionInstanceRegistry(
            [ProductDocumentDefinition::class, ProductDocumentTranslationDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
    }

    /**
     * @return iterable<string, array{string|null, int|null, list<string>}>
     */
    public static function fileProvider(): iterable
    {
        yield 'an allowed file' => ['pdf', 1024, []];
        yield 'an allowed file type in upper case' => ['PDF', 1024, []];
        yield 'another file type' => ['xlsx', 1024, [ProductDocumentValidator::VIOLATION_FILE_EXTENSION_NOT_ALLOWED]];
        yield 'a file over the maximum size' => ['pdf', 1025, [ProductDocumentValidator::VIOLATION_FILE_TOO_LARGE]];
        yield 'a file without content' => [null, null, [ProductDocumentValidator::VIOLATION_FILE_SIZE_UNKNOWN]];
    }

    /**
     * @param list<string> $codes
     */
    #[DataProvider('fileProvider')]
    public function testTheFileMustFitTheLimits(?string $extension, ?int $size, array $codes): void
    {
        $this->file('manual-file', $extension, $size);
        $path = '/0/translations/' . self::GERMAN;

        $errors = $this->validate([$this->translationUpdate('manual', self::GERMAN, $path, file: 'manual-file')]);

        static::assertSame(array_map(static fn (string $code): array => ['code' => $code, 'pointer' => $path . '/mediaId'], $codes), $errors);
    }

    public function testOnlyTheFilesTheWriteSetsAreLoaded(): void
    {
        $this->file('manual-file', 'pdf', 1024);

        $validator = new ProductDocumentValidator(
            new StaticEntityRepository([
                function (Criteria $criteria): MediaCollection {
                    static::assertSame([$this->ids->get('manual-file')], $criteria->getIds());

                    return $this->files;
                },
            ]),
            static::createStub(ProductDocumentReader::class),
            $this->config(),
        );

        $event = $this->event([
            $this->translationUpdate('manual', Defaults::LANGUAGE_SYSTEM, '/0', file: 'manual-file'),
            $this->translationUpdate('manual', self::GERMAN, '/1', file: 'manual-file'),
            // a title change doesn't touch the file
            new UpdateCommand($this->registry->get(ProductDocumentTranslationDefinition::class), ['title' => 'Handbook'], $this->translationKey('datasheet', self::GERMAN), $this->existence(), '/2'),
        ]);
        $validator->validate($event);

        static::assertSame([], $this->errorsOf($event));
    }

    public function testAFileThatDoesNotExistIsLeftToTheDatabase(): void
    {
        static::assertSame([], $this->validate([$this->translationUpdate('manual', Defaults::LANGUAGE_SYSTEM, '/0', file: 'missing-file')]));
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
        yield 'documents the write deletes make room' => [10, [['deleted', '/0'], ['added', '/1']], []];
    }

    /**
     * @param list<array{string, string}> $changes the change and path of each document
     * @param list<string> $pointers
     */
    #[DataProvider('documentCountProvider')]
    public function testAProductCanHaveTenDocuments(int $storedDocuments, array $changes, array $pointers): void
    {
        $deleted = [];
        $commands = [];
        $definition = $this->registry->get(ProductDocumentDefinition::class);
        foreach ($changes as $number => [$change, $path]) {
            $key = ['id' => $this->ids->getBytes('document-' . $number), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)];
            $commands[] = match ($change) {
                'added' => new InsertCommand($definition, $this->productPayload(), $key, $this->existence(), $path),
                // a save through the product sends the product of every document
                'edited' => new UpdateCommand($definition, ['position' => 1, ...$this->productPayload()], $key, $this->existence(), $path),
                'deleted' => new DeleteCommand($definition, $key, $this->existence()),
                default => static::fail('Unknown change ' . $change),
            };
            if ($change === 'deleted') {
                $deleted[] = $key['id'];
            }
        }

        $reader = static::createStub(ProductDocumentReader::class);
        $reader->method('countDocuments')->willReturnCallback(function (array $products, array $excludedIds) use ($storedDocuments, $deleted): array {
            static::assertSame([$this->ids->get('drill') . Defaults::LIVE_VERSION], $products);
            static::assertSame($deleted, $excludedIds);

            return [$products[0] => $storedDocuments - \count($excludedIds)];
        });

        $event = $this->event($commands);
        (new ProductDocumentValidator(new StaticEntityRepository([]), $reader, $this->config()))->validate($event);

        $expected = array_map(static fn (string $pointer): array => ['code' => ProductDocumentValidator::VIOLATION_TOO_MANY_DOCUMENTS, 'pointer' => $pointer], $pointers);
        static::assertSame($expected, $this->errorsOf($event));
    }

    public function testAWriteThatAddsNoDocumentCountsNothing(): void
    {
        $reader = $this->createMock(ProductDocumentReader::class);
        $reader->expects($this->never())->method('countDocuments');

        $definition = $this->registry->get(ProductDocumentDefinition::class);
        $event = $this->event([
            new DeleteCommand($definition, ['id' => $this->ids->getBytes('manual'), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)], $this->existence()),
            new UpdateCommand($definition, ['position' => 2, ...$this->productPayload()], ['id' => $this->ids->getBytes('datasheet'), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)], $this->existence(), '/1'),
        ]);

        (new ProductDocumentValidator(new StaticEntityRepository([]), $reader, $this->config()))->validate($event);

        static::assertSame([], $this->errorsOf($event));
    }

    private function config(): ProductDocumentConfig
    {
        return new ProductDocumentConfig(['pdf', 'docx'], maxPerProduct: 10, maxFileSize: 1024);
    }

    private function file(string $name, ?string $extension, ?int $size): void
    {
        $media = new MediaEntity();
        $media->setId($this->ids->get($name));
        if ($extension !== null) {
            $media->setFileExtension($extension);
        }
        if ($size !== null) {
            $media->setFileSize($size);
        }

        $this->files->add($media);
    }

    /**
     * @param list<WriteCommand> $commands
     *
     * @return list<array{code: mixed, pointer: mixed}>
     */
    private function validate(array $commands): array
    {
        $event = $this->event($commands);
        $reader = static::createStub(ProductDocumentReader::class);
        $reader->method('countDocuments')->willReturnCallback(fn (array $products): array => array_intersect_key($this->storedDocuments, array_flip($products)));

        (new ProductDocumentValidator(new StaticEntityRepository([$this->files]), $reader, $this->config()))->validate($event);

        return $this->errorsOf($event);
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function event(array $commands): PreWriteValidationEvent
    {
        return new PreWriteValidationEvent(WriteContext::createFromContext(Context::createDefaultContext()), $commands);
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

    private function translationUpdate(string $document, string $languageId, string $path, string $file): UpdateCommand
    {
        return new UpdateCommand(
            $this->registry->get(ProductDocumentTranslationDefinition::class),
            ['media_id' => $this->ids->getBytes($file)],
            $this->translationKey($document, $languageId),
            $this->existence(),
            $path,
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

    /**
     * @return array{product_id: string, product_version_id: string}
     */
    private function productPayload(): array
    {
        return ['product_id' => $this->ids->getBytes('drill'), 'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)];
    }

    private function existence(): EntityExistence
    {
        return EntityExistence::createForEntity(ProductDocumentDefinition::ENTITY_NAME, []);
    }
}
