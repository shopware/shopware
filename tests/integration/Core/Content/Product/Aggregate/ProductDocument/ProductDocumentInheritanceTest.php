<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Aggregate\ProductDocument;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
class ProductDocumentInheritanceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->mediaRepository = static::getContainer()->get('media.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testVariantWithoutOwnDocumentsInheritsParentDocuments(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();
        $firstParentDocumentId = Uuid::randomHex();
        $secondParentDocumentId = Uuid::randomHex();
        $firstMediaId = Uuid::randomHex();
        $secondMediaId = Uuid::randomHex();

        $this->createMedia($firstMediaId);
        $this->createMedia($secondMediaId);
        $this->createProductWithVariant($parentId, $variantId, [
            [
                'id' => $firstParentDocumentId,
                'mediaId' => $firstMediaId,
                'position' => 20,
            ],
            [
                'id' => $secondParentDocumentId,
                'mediaId' => $secondMediaId,
                'position' => 10,
            ],
        ]);

        $variant = $this->loadProduct($variantId, true);
        $productDocuments = $variant->getProductDocuments();
        static::assertInstanceOf(ProductDocumentCollection::class, $productDocuments);

        static::assertSame([$secondParentDocumentId, $firstParentDocumentId], array_values($productDocuments->getIds()));
    }

    public function testVariantWithOwnDocumentsReplacesParentDocuments(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();
        $parentDocumentId = Uuid::randomHex();
        $variantDocumentId = Uuid::randomHex();
        $parentMediaId = Uuid::randomHex();
        $variantMediaId = Uuid::randomHex();

        $this->createMedia($parentMediaId);
        $this->createMedia($variantMediaId);
        $this->createProductWithVariant(
            $parentId,
            $variantId,
            [[
                'id' => $parentDocumentId,
                'mediaId' => $parentMediaId,
                'position' => 1,
            ]],
            [[
                'id' => $variantDocumentId,
                'mediaId' => $variantMediaId,
                'position' => 1,
            ]],
        );

        $variant = $this->loadProduct($variantId, true);
        $productDocuments = $variant->getProductDocuments();
        static::assertInstanceOf(ProductDocumentCollection::class, $productDocuments);

        static::assertSame([$variantDocumentId], array_values($productDocuments->getIds()));
    }

    public function testVariantWithoutOwnDocumentsDoesNotLoadParentDocumentsWhenInheritanceIsDisabled(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();
        $parentDocumentId = Uuid::randomHex();
        $parentMediaId = Uuid::randomHex();

        $this->createMedia($parentMediaId);
        $this->createProductWithVariant($parentId, $variantId, [[
            'id' => $parentDocumentId,
            'mediaId' => $parentMediaId,
            'position' => 1,
        ]]);

        $variant = $this->loadProduct($variantId, false);

        $productDocuments = $variant->getProductDocuments();
        static::assertInstanceOf(ProductDocumentCollection::class, $productDocuments);
        static::assertCount(0, $productDocuments);
    }

    /**
     * @param list<array{id: string, mediaId: string, position: int}> $parentDocuments
     * @param list<array{id: string, mediaId: string, position: int}> $variantDocuments
     */
    private function createProductWithVariant(
        string $parentId,
        string $variantId,
        array $parentDocuments,
        array $variantDocuments = []
    ): void {
        $variant = [
            'id' => $variantId,
            'productNumber' => Uuid::randomHex(),
            'name' => 'Variant',
            'stock' => 10,
        ];

        if ($variantDocuments !== []) {
            $variant['productDocuments'] = $variantDocuments;
        }

        $this->productRepository->create([[
            'id' => $parentId,
            'name' => 'Parent product',
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test tax', 'taxRate' => 15],
            'productDocuments' => $parentDocuments,
            'children' => [$variant],
        ]], $this->context);
    }

    private function loadProduct(string $productId, bool $considerInheritance): ProductEntity
    {
        $criteria = new Criteria([$productId]);
        $criteria->getAssociation('productDocuments')
            ->addAssociation('media')
            ->addSorting(new FieldSorting('position'));

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance($considerInheritance);

        $product = $this->productRepository->search($criteria, $context)->get($productId);
        static::assertInstanceOf(ProductEntity::class, $product);

        return $product;
    }

    private function createMedia(string $mediaId): void
    {
        $this->mediaRepository->create([[
            'id' => $mediaId,
            'private' => true,
            'fileName' => 'manual-' . $mediaId,
            'fileExtension' => 'pdf',
            'mimeType' => 'application/pdf',
        ]], $this->context);
    }
}
