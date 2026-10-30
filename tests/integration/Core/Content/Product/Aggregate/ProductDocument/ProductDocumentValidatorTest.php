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

    public function testAProductCanHaveTenDocumentsPerLanguage(): void
    {
        $this->addDocuments(array_map(fn (int $number): array => $this->document('file-' . $number), range(1, 9)));

        $errors = $this->errorsOf(fn () => $this->addDocuments([
            // a write through the product doesn't add the documents it edits
            ['id' => $this->ids->get('file-1-document'), 'title' => 'Renamed'],
            $this->document('file-10'),
            $this->document('file-11'),
        ]));

        // the tenth fits, the eleventh doesn't
        static::assertSame([['code' => ProductDocumentValidator::VIOLATION_TOO_MANY_DOCUMENTS, 'pointer' => '/0/documents/2']], $errors);
        static::assertCount(9, $this->documentTitles());
    }

    public function testTheFilesOfAllLanguagesAreCheckedAsTheyWillBe(): void
    {
        $germanId = $this->getDeDeLanguageId();

        $errors = $this->errorsOf(fn () => $this->addDocuments([
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['title' => 'Manual', 'mediaId' => $this->createMedia('manual')],
                    $germanId => ['title' => 'Handbuch', 'mediaId' => $this->createMedia('handbuch', private: true)],
                ],
            ],
            // a file the same write creates has no content yet
            ['translations' => [Defaults::LANGUAGE_SYSTEM => ['title' => 'Datasheet', 'media' => ['id' => Uuid::randomHex()]]]],
        ]));

        static::assertSame([
            ['code' => ProductDocumentValidator::VIOLATION_FILE_PRIVATE, 'pointer' => '/0/documents/0/translations/' . $germanId . '/mediaId'],
            ['code' => ProductDocumentValidator::VIOLATION_FILE_SIZE_UNKNOWN, 'pointer' => $this->defaultTranslationPointer(1) . '/mediaId'],
        ], $errors);
    }

    public function testADocumentNeedsAFileInTheDefaultLanguage(): void
    {
        $errors = $this->errorsOf(fn () => $this->addDocuments([
            ['title' => 'Manual', 'mediaId' => $this->createMedia('manual')],
            ['translations' => [$this->getDeDeLanguageId() => ['title' => 'Zertifikat', 'mediaId' => $this->createMedia('zertifikat')]]],
        ]));

        static::assertSame([['code' => ProductDocumentValidator::VIOLATION_DEFAULT_TRANSLATION_MISSING, 'pointer' => '/0/documents/1']], $errors);
    }

    public function testAFileCanBeADocumentOfAProductOnlyOnce(): void
    {
        $this->addDocuments([['title' => 'Manual', 'mediaId' => $this->createMedia('manual')]]);

        $errors = $this->errorsOf(fn () => $this->addDocuments([['title' => 'Operating manual', 'mediaId' => $this->ids->get('manual')]]));

        static::assertSame([['code' => ProductDocumentValidator::VIOLATION_FILE_ALREADY_USED, 'pointer' => $this->defaultTranslationPointer(0) . '/mediaId']], $errors);
        static::assertSame(['Manual'], $this->documentTitles());
    }

    /**
     * @param list<array<string, mixed>> $documents
     */
    private function addDocuments(array $documents): void
    {
        $this->productRepository->update([['id' => $this->ids->get('drill'), 'documents' => $documents]], $this->context);
    }

    /**
     * @return array{id: string, title: string, mediaId: string}
     */
    private function document(string $fileName): array
    {
        return ['id' => $this->ids->create($fileName . '-document'), 'title' => $fileName, 'mediaId' => $this->createMedia($fileName)];
    }

    private function createMedia(string $fileName, bool $private = false): string
    {
        $this->mediaRepository->create([[
            'id' => $this->ids->create($fileName),
            'fileName' => $fileName,
            'fileExtension' => 'pdf',
            'fileSize' => 1024,
            'private' => $private,
        ]], $this->context);

        return $this->ids->get($fileName);
    }

    /**
     * Where the default-language translation of a written document is, when the document sets its title and file directly.
     */
    private function defaultTranslationPointer(int $document): string
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
