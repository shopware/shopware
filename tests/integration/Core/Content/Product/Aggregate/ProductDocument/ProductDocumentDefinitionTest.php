<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Aggregate\ProductDocument;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('inventory')]
class ProductDocumentDefinitionTest extends TestCase
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
    }

    public function testAFileCanBeLinkedToManyProductsAndLanguages(): void
    {
        $manualId = $this->createMedia('manual');
        $germanId = $this->getDeDeLanguageId();

        $this->createProduct('drill', documents: [
            ['languageId' => Defaults::LANGUAGE_SYSTEM, 'mediaId' => $manualId, 'title' => 'Manual', 'position' => 2],
            ['languageId' => $germanId, 'mediaId' => $manualId, 'title' => 'Handbuch', 'position' => 1],
        ]);
        $this->createProduct('saw', documents: [
            ['languageId' => Defaults::LANGUAGE_SYSTEM, 'mediaId' => $manualId, 'title' => 'Manual'],
        ]);

        $drillDocuments = $this->loadDocuments('drill');
        static::assertSame(['Handbuch', 'Manual'], array_values($drillDocuments->map(static fn (ProductDocumentEntity $document) => $document->getTitle())));
        static::assertSame([$germanId, Defaults::LANGUAGE_SYSTEM], array_values($drillDocuments->map(static fn (ProductDocumentEntity $document) => $document->getLanguageId())));
        static::assertSame([$manualId, $manualId], array_values($drillDocuments->map(static fn (ProductDocumentEntity $document) => $document->getMedia()?->getId())));

        $sawDocument = $this->loadDocuments('saw')->first();
        static::assertNotNull($sawDocument);
        static::assertSame($manualId, $sawDocument->getMediaId());
        static::assertSame(0, $sawDocument->getPosition());
    }

    public function testAFileCanBeLinkedOnlyOncePerProductAndLanguage(): void
    {
        $manualId = $this->createMedia('manual');
        $this->createProduct('drill', documents: [
            ['languageId' => Defaults::LANGUAGE_SYSTEM, 'mediaId' => $manualId, 'title' => 'Manual'],
        ]);

        $this->expectExceptionObject(ProductException::duplicateDocument());

        $this->productDocumentRepository->create([[
            'productId' => $this->ids->get('drill'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'mediaId' => $manualId,
            'title' => 'Manual again',
        ]], $this->context);
    }

    public function testVariantsWithoutOwnDocumentsInheritTheDocumentsOfTheirParent(): void
    {
        $manualId = $this->createMedia('manual');
        $this->createProduct(
            'drill',
            documents: [['languageId' => Defaults::LANGUAGE_SYSTEM, 'mediaId' => $manualId, 'title' => 'Manual']],
            variants: ['drill.blue' => []],
        );

        $inherited = $this->loadDocuments('drill.blue', considerInheritance: true);
        static::assertCount(1, $inherited);
        static::assertSame($this->ids->get('drill'), $inherited->first()?->getProductId());

        static::assertCount(0, $this->loadDocuments('drill.blue', considerInheritance: false));
    }

    public function testDocumentsOfAVariantReplaceTheDocumentsOfItsParent(): void
    {
        $manualId = $this->createMedia('manual');
        $blueManualId = $this->createMedia('blue-manual');
        $this->createProduct(
            'drill',
            documents: [['languageId' => Defaults::LANGUAGE_SYSTEM, 'mediaId' => $manualId, 'title' => 'Manual']],
            variants: ['drill.blue' => [['languageId' => Defaults::LANGUAGE_SYSTEM, 'mediaId' => $blueManualId, 'title' => 'Blue manual']]],
        );

        $documents = $this->loadDocuments('drill.blue', considerInheritance: true);
        static::assertCount(1, $documents);
        static::assertSame($blueManualId, $documents->first()?->getMediaId());
    }

    public function testDeletingAProductDeletesItsDocumentsButKeepsTheFiles(): void
    {
        $manualId = $this->createMedia('manual');
        $this->createProduct('drill', documents: [
            ['languageId' => Defaults::LANGUAGE_SYSTEM, 'mediaId' => $manualId, 'title' => 'Manual'],
        ]);

        $this->productRepository->delete([['id' => $this->ids->get('drill')]], $this->context);

        $criteria = (new Criteria())->addFilter(new EqualsFilter('productId', $this->ids->get('drill')));
        static::assertSame([], $this->productDocumentRepository->searchIds($criteria, $this->context)->getIds());
        static::assertSame([$manualId], $this->mediaRepository->searchIds(new Criteria([$manualId]), $this->context)->getIds());
    }

    public function testDeletingALanguageDeletesTheDocumentsOfThatLanguage(): void
    {
        $manualId = $this->createMedia('manual');
        $languageId = $this->createLanguage('Documents test language');
        $this->createProduct('drill', documents: [
            ['languageId' => Defaults::LANGUAGE_SYSTEM, 'mediaId' => $manualId, 'title' => 'Manual'],
            ['languageId' => $languageId, 'mediaId' => $manualId, 'title' => 'Manual in the test language'],
        ]);

        /** @var EntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = static::getContainer()->get('language.repository');
        $languageRepository->delete([['id' => $languageId]], $this->context);

        $documents = $this->loadDocuments('drill');
        static::assertSame([Defaults::LANGUAGE_SYSTEM], array_values($documents->map(static fn (ProductDocumentEntity $document) => $document->getLanguageId())));
    }

    public function testDeletingAFileDeletesItsDocuments(): void
    {
        $manualId = $this->createMedia('manual');
        $datasheetId = $this->createMedia('datasheet');
        $this->createProduct('drill', documents: [
            ['languageId' => Defaults::LANGUAGE_SYSTEM, 'mediaId' => $manualId, 'title' => 'Manual'],
            ['languageId' => Defaults::LANGUAGE_SYSTEM, 'mediaId' => $datasheetId, 'title' => 'Datasheet'],
        ]);

        $this->mediaRepository->delete([['id' => $manualId]], $this->context);

        $documents = $this->loadDocuments('drill');
        static::assertSame(['Datasheet'], array_values($documents->map(static fn (ProductDocumentEntity $document) => $document->getTitle())));
    }

    public function testCloningAProductCopiesItsDocumentsWithTheSameFiles(): void
    {
        $manualId = $this->createMedia('manual');
        $this->createProduct('drill', documents: [
            ['languageId' => Defaults::LANGUAGE_SYSTEM, 'mediaId' => $manualId, 'title' => 'Manual', 'position' => 3],
        ]);

        $this->productRepository->clone(
            $this->ids->get('drill'),
            $this->context,
            $this->ids->create('drill-copy'),
            new CloneBehavior(['productNumber' => 'drill-copy'])
        );

        $original = $this->loadDocuments('drill')->first();
        $copy = $this->loadDocuments('drill-copy')->first();
        static::assertNotNull($original);
        static::assertNotNull($copy);
        static::assertNotSame($original->getId(), $copy->getId());
        static::assertSame($manualId, $copy->getMediaId());
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $copy->getLanguageId());
        static::assertSame('Manual', $copy->getTitle());
        static::assertSame(3, $copy->getPosition());
    }

    /**
     * @param list<array<string, mixed>> $documents
     * @param array<string, list<array<string, mixed>>> $variants documents per variant number
     */
    private function createProduct(string $number, array $documents = [], array $variants = []): void
    {
        $builder = (new ProductBuilder($this->ids, $number))->price(10);

        foreach ($variants as $variantNumber => $variantDocuments) {
            $variant = (new ProductBuilder($this->ids, $variantNumber))->build();
            $variant['documents'] = $variantDocuments;
            $builder->variant($variant);
        }

        $product = $builder->build();
        $product['documents'] = $documents;

        $this->productRepository->create([$product], $this->context);
    }

    private function loadDocuments(string $productNumber, bool $considerInheritance = false): ProductDocumentCollection
    {
        $criteria = new Criteria([$this->ids->get($productNumber)]);
        $criteria->getAssociation('documents')
            ->addAssociation('media')
            ->addSorting(new FieldSorting('position'));

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance($considerInheritance);

        $product = $this->productRepository->search($criteria, $context)->getEntities()->first();
        static::assertInstanceOf(ProductEntity::class, $product);

        $documents = $product->getDocuments();
        static::assertInstanceOf(ProductDocumentCollection::class, $documents);

        return $documents;
    }

    private function createMedia(string $key): string
    {
        $this->mediaRepository->create([[
            'id' => $this->ids->create($key),
            'fileName' => $key,
            'fileExtension' => 'pdf',
            'mimeType' => 'application/pdf',
        ]], $this->context);

        return $this->ids->get($key);
    }

    private function createLanguage(string $name): string
    {
        $localeId = $this->getLocaleIdOfSystemLanguage();

        static::getContainer()->get('language.repository')->create([[
            'id' => $this->ids->create($name),
            'name' => $name,
            'localeId' => $localeId,
            'translationCodeId' => $localeId,
        ]], $this->context);

        return $this->ids->get($name);
    }
}
