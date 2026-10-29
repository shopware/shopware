<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Aggregate\ProductDocument;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentValidator;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('inventory')]
class ProductDocumentValidatorTest extends TestCase
{
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private const MAX_FILE_SIZE = 20 * 1024 * 1024;

    private IdsCollection $ids;

    private Context $context;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    /**
     * @var EntityRepository<ProductDocumentCollection>
     */
    private EntityRepository $productDocumentRepository;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = Context::createDefaultContext();
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->productDocumentRepository = static::getContainer()->get('product_document.repository');
        $this->mediaRepository = static::getContainer()->get('media.repository');

        $this->productRepository->create([(new ProductBuilder($this->ids, 'drill'))->price(10)->build()], $this->context);
    }

    public function testAFileFromAnyMediaFolderCanBeUsed(): void
    {
        $folderId = Uuid::randomHex();
        static::getContainer()->get('media_folder.repository')->create([
            ['id' => $folderId, 'name' => 'Downloads', 'configurationId' => Uuid::randomHex()],
        ], $this->context);

        $this->addDocuments([['title' => 'Manual', 'mediaId' => $this->createMedia('manual', folderId: $folderId)]]);

        static::assertSame(['Manual'], $this->documentTitles());
    }

    public function testAProductCanHaveTenDocumentsPerLanguage(): void
    {
        $this->addDocuments(array_map(fn (int $number): array => $this->document('file-' . $number), range(1, 9)));

        $errors = $this->errorsOf(fn () => $this->addDocuments([$this->document('file-10'), $this->document('file-11')]));

        // the tenth fits, the eleventh doesn't
        static::assertSame([['code' => ProductDocumentValidator::VIOLATION_TOO_MANY_DOCUMENTS, 'pointer' => '/0/documents/1']], $errors);
        static::assertCount(9, $this->documentTitles());
    }

    public function testTheFileTypeMustBeAllowed(): void
    {
        $errors = $this->errorsOf(fn () => $this->addDocuments([
            ['title' => 'Manual', 'mediaId' => $this->createMedia('manual')],
            ['title' => 'Spare parts', 'mediaId' => $this->createMedia('spare-parts', extension: 'xlsx')],
        ]));

        static::assertSame([['code' => ProductDocumentValidator::VIOLATION_FILE_EXTENSION_NOT_ALLOWED, 'pointer' => $this->defaultVersionPointer(1) . '/mediaId']], $errors);
    }

    public function testTheFileCanHaveAtMostTheMaximumSize(): void
    {
        $this->addDocuments([['title' => 'Manual', 'mediaId' => $this->createMedia('manual', size: self::MAX_FILE_SIZE)]]);

        $errors = $this->errorsOf(fn () => $this->addDocuments([
            ['title' => 'Catalog', 'mediaId' => $this->createMedia('catalog', size: self::MAX_FILE_SIZE + 1)],
        ]));

        static::assertSame([['code' => ProductDocumentValidator::VIOLATION_FILE_TOO_LARGE, 'pointer' => $this->defaultVersionPointer(0) . '/mediaId']], $errors);
    }

    public function testTheFileSizeMustBeKnown(): void
    {
        $externalFile = $this->createMedia('manual', size: null, path: 'https://cdn.example.com/manual.pdf');

        $errors = $this->errorsOf(fn () => $this->addDocuments([['title' => 'Manual', 'mediaId' => $externalFile]]));

        static::assertSame([['code' => ProductDocumentValidator::VIOLATION_FILE_SIZE_UNKNOWN, 'pointer' => $this->defaultVersionPointer(0) . '/mediaId']], $errors);
    }

    public function testTheFileMustBePublic(): void
    {
        $errors = $this->errorsOf(fn () => $this->addDocuments([['title' => 'Manual', 'mediaId' => $this->createMedia('manual', private: true)]]));

        static::assertSame([['code' => ProductDocumentValidator::VIOLATION_FILE_PRIVATE, 'pointer' => $this->defaultVersionPointer(0) . '/mediaId']], $errors);
    }

    public function testTheFilesOfOtherLanguagesAreCheckedToo(): void
    {
        $germanId = $this->getDeDeLanguageId();

        $errors = $this->errorsOf(fn () => $this->addDocuments([[
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['title' => 'Manual', 'mediaId' => $this->createMedia('manual')],
                $germanId => ['title' => 'Handbuch', 'mediaId' => $this->createMedia('handbuch', private: true)],
            ],
        ]]));

        static::assertSame([['code' => ProductDocumentValidator::VIOLATION_FILE_PRIVATE, 'pointer' => '/0/documents/0/translations/' . $germanId . '/mediaId']], $errors);
    }

    public function testADocumentNeedsAFileInTheDefaultLanguage(): void
    {
        $errors = $this->errorsOf(fn () => $this->addDocuments([
            ['title' => 'Manual', 'mediaId' => $this->createMedia('manual')],
            ['translations' => [$this->getDeDeLanguageId() => ['title' => 'Zertifikat', 'mediaId' => $this->createMedia('zertifikat')]]],
        ]));

        static::assertSame([['code' => ProductDocumentValidator::VIOLATION_DEFAULT_VERSION_MISSING, 'pointer' => '/0/documents/1']], $errors);
    }

    public function testAWriteOnlyChecksWhatItChanges(): void
    {
        $documentId = $this->ids->create('manual-document');
        $this->addDocuments([['id' => $documentId, 'title' => 'Manual', 'mediaId' => $this->createMedia('manual')]]);

        // the file is no longer valid, which doesn't stop changes that keep it
        $this->mediaRepository->update([['id' => $this->ids->get('manual'), 'private' => true]], $this->context);
        $this->productDocumentRepository->update([['id' => $documentId, 'title' => 'Operating manual', 'position' => 2]], $this->context);

        static::assertSame(['Operating manual'], $this->documentTitles());
    }

    /**
     * @param list<array<string, mixed>> $documents
     */
    private function addDocuments(array $documents): void
    {
        $this->productRepository->update([['id' => $this->ids->get('drill'), 'documents' => $documents]], $this->context);
    }

    /**
     * @return array{title: string, mediaId: string}
     */
    private function document(string $fileName): array
    {
        return ['title' => $fileName, 'mediaId' => $this->createMedia($fileName)];
    }

    private function createMedia(string $fileName, string $extension = 'pdf', ?int $size = 1024, bool $private = false, ?string $path = null, ?string $folderId = null): string
    {
        $this->mediaRepository->create([[
            'id' => $this->ids->create($fileName),
            'fileName' => $fileName,
            'fileExtension' => $extension,
            'fileSize' => $size,
            'private' => $private,
            'path' => $path,
            'mediaFolderId' => $folderId,
        ]], $this->context);

        return $this->ids->get($fileName);
    }

    /**
     * Where the version in the default language of a written document is, when the document sets its title and file directly.
     */
    private function defaultVersionPointer(int $document): string
    {
        return '/0/documents/' . $document . '/translations/' . Defaults::LANGUAGE_SYSTEM;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function errorsOf(\Closure $write): array
    {
        try {
            $write();
        } catch (WriteException $exception) {
            $errors = [];
            foreach ($exception->getErrors() as $error) {
                $errors[] = ['code' => $error['code'], 'pointer' => $error['source']['pointer'] ?? null];
            }

            return $errors;
        }

        static::fail('The write did not fail.');
    }

    /**
     * @return list<string|null>
     */
    private function documentTitles(): array
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('productId', $this->ids->get('drill')));

        return array_values($this->productDocumentRepository->search($criteria, $this->context)->getEntities()->map(
            static fn (ProductDocumentEntity $document): ?string => $document->getTitle(),
        ));
    }
}
