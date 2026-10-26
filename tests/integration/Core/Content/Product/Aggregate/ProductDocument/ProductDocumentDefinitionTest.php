<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Aggregate\ProductDocument;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
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

    public function testEachDocumentFallsBackToItsDefaultVersionOnItsOwn(): void
    {
        $germanId = $this->getDeDeLanguageId();
        $this->createProduct('drill', documents: [
            [
                'position' => 1,
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['title' => 'Certificate', 'mediaId' => $this->createMedia('certificate-en')],
                    $germanId => ['title' => 'Zertifikat', 'mediaId' => $this->createMedia('certificate-de')],
                ],
            ],
            ['position' => 2, 'title' => 'Operating manual', 'mediaId' => $this->createMedia('manual-en')],
        ]);

        $german = $this->loadDocuments('drill', languageIdChain: [$germanId, Defaults::LANGUAGE_SYSTEM]);
        static::assertSame(['Zertifikat', 'Operating manual'], $this->translated($german, 'title'));
        static::assertSame([$this->ids->get('certificate-de'), $this->ids->get('manual-en')], $this->translated($german, 'mediaId'));

        $english = $this->loadDocuments('drill');
        static::assertSame(['Certificate', 'Operating manual'], $this->translated($english, 'title'));
        static::assertSame([$this->ids->get('certificate-en'), $this->ids->get('manual-en')], $this->translated($english, 'mediaId'));
    }

    public function testAVersionInAnotherLanguageMayOnlyChangeTheTitle(): void
    {
        $germanId = $this->getDeDeLanguageId();
        $this->createProduct('drill', documents: [[
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['title' => 'Certificate', 'mediaId' => $this->createMedia('certificate-en')],
                $germanId => ['title' => 'Zertifikat'],
            ],
        ]]);

        $german = $this->loadDocuments('drill', languageIdChain: [$germanId, Defaults::LANGUAGE_SYSTEM]);
        static::assertSame(['Zertifikat'], $this->translated($german, 'title'));
        static::assertSame([$this->ids->get('certificate-en')], $this->translated($german, 'mediaId'));
    }

    public function testANewDocumentNeedsAFileInTheDefaultLanguage(): void
    {
        $this->expectException(WriteException::class);

        $this->createProduct('drill', documents: [['title' => 'Certificate']]);
    }

    public function testAFileCanBeUsedByManyProductsAndLanguages(): void
    {
        $germanId = $this->getDeDeLanguageId();
        $manualId = $this->createMedia('manual');
        $this->createProduct('drill', documents: [[
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['title' => 'Manual', 'mediaId' => $manualId],
                $germanId => ['title' => 'Handbuch', 'mediaId' => $manualId],
            ],
        ]]);
        $this->createProduct('saw', documents: [['title' => 'Manual', 'mediaId' => $manualId]]);

        static::assertSame([$manualId], $this->translated($this->loadDocuments('drill', languageIdChain: [$germanId, Defaults::LANGUAGE_SYSTEM]), 'mediaId'));
        static::assertSame([$manualId], $this->translated($this->loadDocuments('saw'), 'mediaId'));
    }

    public function testVariantsWithoutOwnDocumentsInheritTheDocumentsOfTheirParent(): void
    {
        $this->createProduct(
            'drill',
            documents: [['title' => 'Manual', 'mediaId' => $this->createMedia('manual')]],
            variants: ['drill.blue' => []],
        );

        $inherited = $this->loadDocuments('drill.blue', considerInheritance: true);
        static::assertCount(1, $inherited);
        static::assertSame($this->ids->get('drill'), $inherited->first()?->getProductId());

        static::assertCount(0, $this->loadDocuments('drill.blue'));
    }

    public function testDocumentsOfAVariantReplaceTheDocumentsOfItsParent(): void
    {
        $this->createProduct(
            'drill',
            documents: [['title' => 'Manual', 'mediaId' => $this->createMedia('manual')]],
            variants: ['drill.blue' => [['title' => 'Blue manual', 'mediaId' => $this->createMedia('blue-manual')]]],
        );

        $documents = $this->loadDocuments('drill.blue', considerInheritance: true);
        static::assertSame([$this->ids->get('blue-manual')], $this->translated($documents, 'mediaId'));
    }

    public function testDeletingAProductDeletesItsDocumentsButKeepsTheFiles(): void
    {
        $manualId = $this->createMedia('manual');
        $this->createProduct('drill', documents: [['title' => 'Manual', 'mediaId' => $manualId]]);

        $this->productRepository->delete([['id' => $this->ids->get('drill')]], $this->context);

        $criteria = (new Criteria())->addFilter(new EqualsFilter('productId', $this->ids->get('drill')));
        static::assertSame([], $this->productDocumentRepository->searchIds($criteria, $this->context)->getIds());
        static::assertSame([$manualId], $this->mediaRepository->searchIds(new Criteria([$manualId]), $this->context)->getIds());
    }

    public function testDeletingALanguageDeletesTheVersionsInThatLanguage(): void
    {
        $languageId = $this->createLanguage('Documents test language');
        $this->createProduct('drill', documents: [[
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['title' => 'Manual', 'mediaId' => $this->createMedia('manual-en')],
                $languageId => ['title' => 'Manual in the test language', 'mediaId' => $this->createMedia('manual-test')],
            ],
        ]]);

        /** @var EntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = static::getContainer()->get('language.repository');
        $languageRepository->delete([['id' => $languageId]], $this->context);

        static::assertSame([[Defaults::LANGUAGE_SYSTEM]], $this->translationLanguageIds('drill'));
    }

    public function testDeletingTheFileOfAVersionFallsBackToTheDefaultVersion(): void
    {
        $germanId = $this->getDeDeLanguageId();
        $this->createProduct('drill', documents: [[
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['title' => 'Certificate', 'mediaId' => $this->createMedia('certificate-en')],
                $germanId => ['title' => 'Zertifikat', 'mediaId' => $this->createMedia('certificate-de')],
            ],
        ]]);

        $this->mediaRepository->delete([['id' => $this->ids->get('certificate-de')]], $this->context);

        $german = $this->loadDocuments('drill', languageIdChain: [$germanId, Defaults::LANGUAGE_SYSTEM]);
        static::assertSame(['Certificate'], $this->translated($german, 'title'));
        static::assertSame([$this->ids->get('certificate-en')], $this->translated($german, 'mediaId'));
    }

    public function testDeletingTheFileOfTheDefaultVersionDeletesTheDocument(): void
    {
        $germanId = $this->getDeDeLanguageId();
        $this->createProduct('drill', documents: [
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['title' => 'Certificate', 'mediaId' => $this->createMedia('certificate-en')],
                    $germanId => ['title' => 'Zertifikat', 'mediaId' => $this->createMedia('certificate-de')],
                ],
            ],
            ['title' => 'Manual', 'mediaId' => $this->createMedia('manual-en')],
        ]);

        $this->mediaRepository->delete([['id' => $this->ids->get('certificate-en')]], $this->context);

        static::assertSame(['Manual'], $this->translated($this->loadDocuments('drill'), 'title'));
        static::assertSame([$this->ids->get('certificate-de')], $this->mediaRepository->searchIds(new Criteria([$this->ids->get('certificate-de')]), $this->context)->getIds());
    }

    public function testCloningAProductCopiesItsDocumentsWithTheirVersions(): void
    {
        $germanId = $this->getDeDeLanguageId();
        $this->createProduct('drill', documents: [[
            'position' => 3,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['title' => 'Certificate', 'mediaId' => $this->createMedia('certificate-en')],
                $germanId => ['title' => 'Zertifikat', 'mediaId' => $this->createMedia('certificate-de')],
            ],
        ]]);

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
        static::assertSame(3, $copy->getPosition());

        $expected = [Defaults::LANGUAGE_SYSTEM => $this->ids->get('certificate-en'), $germanId => $this->ids->get('certificate-de')];
        ksort($expected);
        static::assertSame($expected, $this->mediaIdsByLanguage($copy));
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

    /**
     * @param non-empty-list<string> $languageIdChain
     */
    private function loadDocuments(string $productNumber, array $languageIdChain = [Defaults::LANGUAGE_SYSTEM], bool $considerInheritance = false): ProductDocumentCollection
    {
        $criteria = new Criteria([$this->ids->get($productNumber)]);
        $criteria->getAssociation('documents')
            ->addAssociation('translations')
            ->addSorting(new FieldSorting('position'));

        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, $languageIdChain);
        $context->setConsiderInheritance($considerInheritance);

        $product = $this->productRepository->search($criteria, $context)->getEntities()->first();
        static::assertInstanceOf(ProductEntity::class, $product);

        $documents = $product->getDocuments();
        static::assertInstanceOf(ProductDocumentCollection::class, $documents);

        return $documents;
    }

    /**
     * @return list<mixed>
     */
    private function translated(ProductDocumentCollection $documents, string $field): array
    {
        return array_values($documents->map(static fn (ProductDocumentEntity $document) => $document->getTranslation($field)));
    }

    /**
     * @return list<list<string>>
     */
    private function translationLanguageIds(string $productNumber): array
    {
        return array_values($this->loadDocuments($productNumber)->map(
            static fn (ProductDocumentEntity $document) => array_values($document->getTranslations()?->map(
                static fn (ProductDocumentTranslationEntity $translation) => $translation->getLanguageId()
            ) ?? [])
        ));
    }

    /**
     * @return array<string, string|null>
     */
    private function mediaIdsByLanguage(ProductDocumentEntity $document): array
    {
        $mediaIds = [];
        foreach ($document->getTranslations() ?? [] as $translation) {
            $mediaIds[$translation->getLanguageId()] = $translation->getMediaId();
        }
        ksort($mediaIds);

        return $mediaIds;
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
