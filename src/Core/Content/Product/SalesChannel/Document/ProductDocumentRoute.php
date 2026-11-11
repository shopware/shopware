<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Document;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Extension\ProductDocumentRouteExtension;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('inventory')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ProductDocumentRoute
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<ProductCollection> $productRepository
     * @param EntityRepository<ProductDocumentCollection> $productDocumentRepository
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $productRepository,
        private readonly EntityRepository $productDocumentRepository,
        private readonly EntityRepository $mediaRepository,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    #[Route(
        path: '/store-api/product/{productId}/documents',
        name: 'store-api.product.documents',
        methods: [Request::METHOD_POST, Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => ProductDocumentDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]
    )]
    public function load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductDocumentRouteResponse
    {
        return $this->extensions->publish(
            name: ProductDocumentRouteExtension::NAME,
            extension: new ProductDocumentRouteExtension($productId, $request, $context, $criteria),
            function: fn (string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductDocumentRouteResponse => $this->_load($productId, $context, $criteria),
        );
    }

    private function _load(string $productId, SalesChannelContext $context, Criteria $criteria): ProductDocumentRouteResponse
    {
        $this->cacheTagCollector->addTag(ProductDetailRoute::buildName($this->getParentId($productId, $context)));

        $criteria->setTitle('product-document-route');
        $criteria->addFilter(new EqualsFilter('product.id', $productId));
        if ($criteria->getSorting() === []) {
            $criteria->addSorting(new FieldSorting('position'));
        }

        $result = $this->productDocumentRepository->search($criteria, $context->getContext());

        return new ProductDocumentRouteResponse($this->withPublicFiles($result, $context->getContext()));
    }

    private function getParentId(string $productId, SalesChannelContext $context): string
    {
        if (!Uuid::isValid($productId)) {
            throw ProductException::productNotFound($productId);
        }

        $criteria = new Criteria([$productId]);
        $criteria->setTitle('product-document-route::product');
        $criteria->addFields(['parentId']);
        $criteria->addFilter(new ProductAvailableFilter($context->getSalesChannelId(), ProductVisibilityDefinition::VISIBILITY_LINK));

        $product = $this->productRepository->search($criteria, $context)->getEntities()->first();
        if ($product === null) {
            throw ProductException::productNotFound($productId);
        }

        $parentId = $product->get('parentId');

        return \is_string($parentId) ? $parentId : $productId;
    }

    /**
     * @param EntitySearchResult<ProductDocumentCollection> $result
     *
     * @return EntitySearchResult<ProductDocumentCollection>
     */
    private function withPublicFiles(EntitySearchResult $result, Context $context): EntitySearchResult
    {
        $documents = $result->getEntities();

        $mediaIds = array_filter(
            $documents->map(static fn (ProductDocumentEntity $document): mixed => $document->getTranslation('mediaId')),
            \is_string(...)
        );

        $media = new MediaCollection();
        if ($mediaIds !== []) {
            $mediaCriteria = new Criteria(array_values(array_unique($mediaIds)));
            $mediaCriteria->setTitle('product-document-route::media');
            $mediaCriteria->addFilter(new EqualsFilter('private', false));

            $media = $this->mediaRepository->search($mediaCriteria, $context)->getEntities();
        }

        foreach ($documents as $document) {
            $document->setMedia($media->get($mediaIds[$document->getId()] ?? ''));
        }

        $withFiles = $documents->filter(static fn (ProductDocumentEntity $document): bool => $document->getMedia() !== null);

        return new EntitySearchResult(
            ProductDocumentDefinition::ENTITY_NAME,
            $result->getTotal() - ($documents->count() - $withFiles->count()),
            $withFiles,
            $result->getAggregations(),
            $result->getCriteria(),
            $context,
        );
    }
}
