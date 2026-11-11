<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Document;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Extension\ProductDocumentRouteExtension;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
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
        $this->cacheTagCollector->addTag(ProductDetailRoute::buildName($this->getVisibleParentId($productId, $context)));

        $media = $this->loadPublicMedia($productId, $context->getContext());

        $criteria->setTitle('product-document-route');
        $criteria->addFilter(
            new EqualsFilter('product.id', $productId),
            new EqualsAnyFilter('mediaId', array_values($media->getIds())),
        );
        if ($criteria->getSorting() === []) {
            $criteria->addSorting(new FieldSorting('position'), new FieldSorting('id'));
        }
        if ($criteria->hasAssociation('translations')) {
            $criteria->getAssociation('translations')->addFilter(new EqualsFilter('media.private', false));
        }

        $result = $this->productDocumentRepository->search($criteria, $context->getContext());

        foreach ($result->getEntities() as $document) {
            $mediaId = $document->getTranslation('mediaId');
            $document->setMedia(\is_string($mediaId) ? $media->get($mediaId) : null);
        }

        return new ProductDocumentRouteResponse($result);
    }

    private function getVisibleParentId(string $productId, SalesChannelContext $context): string
    {
        if (!Uuid::isValid($productId)) {
            throw ProductException::productNotFound($productId);
        }

        $criteria = new Criteria([$productId]);
        $criteria->setTitle('product-document-route::product');
        $criteria->addFields(['parentId']);

        $product = $this->productRepository->search($criteria, $context)->getEntities()->first();
        if ($product === null) {
            throw ProductException::productNotFound($productId);
        }

        $parentId = $product->get('parentId');

        return \is_string($parentId) ? $parentId : $productId;
    }

    private function loadPublicMedia(string $productId, Context $context): MediaCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('product-document-route::media-ids');
        $criteria->addFilter(new EqualsFilter('product.id', $productId));
        $criteria->addAggregation(new TermsAggregation('media-ids', 'mediaId'));

        $mediaIds = $this->productDocumentRepository->aggregate($criteria, $context)->get('media-ids');
        if (!$mediaIds instanceof TermsResult || $mediaIds->getKeys() === []) {
            return new MediaCollection();
        }

        $mediaCriteria = new Criteria($mediaIds->getKeys());
        $mediaCriteria->setTitle('product-document-route::media');
        $mediaCriteria->addFilter(new EqualsFilter('private', false));

        return $this->mediaRepository->search($mediaCriteria, $context)->getEntities();
    }
}
