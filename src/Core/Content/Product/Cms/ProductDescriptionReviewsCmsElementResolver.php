<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductDescriptionReviewsStruct;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewLoader;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewsWidgetLoadedHook;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[Package('discovery')]
class ProductDescriptionReviewsCmsElementResolver extends AbstractProductDetailCmsElementResolver
{
    final public const TYPE = 'product-description-reviews';

    /**
     * @internal
     *
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly AbstractProductReviewLoader $productReviewLoader,
        private readonly ScriptExecutor $scriptExecutor,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $mediaRepository,
    ) {
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $criteriaCollection = parent::collect($slot, $resolverContext);

        $documentCriteria = $this->createDocumentCriteria($slot, $resolverContext);
        if ($documentCriteria === null) {
            return $criteriaCollection;
        }

        $criteriaCollection ??= new CriteriaCollection();
        $criteriaCollection->add(self::getDocumentsKey($slot), ProductDocumentDefinition::class, $documentCriteria);

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $data = new ProductDescriptionReviewsStruct();
        $slot->setData($data);

        $productConfig = $slot->getFieldConfig()->get('product');
        if ($productConfig === null) {
            return;
        }

        $request = $resolverContext->getRequest();
        $ratingSuccess = (bool) RequestParamHelper::get($request, 'success', false);
        $data->setRatingSuccess($ratingSuccess);

        $product = null;

        if ($productConfig->isMapped() && $resolverContext instanceof EntityResolverContext) {
            $product = $this->resolveEntityValue($resolverContext->getEntity(), $productConfig->getStringValue());
        }

        if ($productConfig->isStatic()) {
            $product = $this->getSlotProduct($slot, $result, $productConfig->getStringValue());
        }

        if (!$product instanceof SalesChannelProductEntity) {
            // product can not be resolved, so we do not enrich the slot
            return;
        }

        $data->setProduct($product);
        $this->addDocuments($data, $product, $result->get(self::getDocumentsKey($slot))?->getEntities(), $resolverContext->getSalesChannelContext()->getContext());

        if ($this->systemConfigService->getBool('core.listing.showReview', $resolverContext->getSalesChannelContext()->getSalesChannelId())) {
            $reviews = $this->productReviewLoader->load($request, $resolverContext->getSalesChannelContext(), $product->getId(), $product->getParentId());

            $this->scriptExecutor->execute(new ProductReviewsWidgetLoadedHook($reviews, $resolverContext->getSalesChannelContext()));

            $data->setReviews($reviews);
        }
    }

    private static function getDocumentsKey(CmsSlotEntity $slot): string
    {
        return 'product_documents_' . $slot->getUniqueIdentifier();
    }

    private function createDocumentCriteria(CmsSlotEntity $slot, ResolverContext $resolverContext): ?Criteria
    {
        $criteria = new Criteria();
        $criteria->setTitle('cms::product-description-reviews-documents');
        $criteria->addSorting(new FieldSorting('position'));

        // `product.id` resolves the documents a variant inherits from its parent
        if ($resolverContext instanceof EntityResolverContext && $resolverContext->getDefinition() instanceof SalesChannelProductDefinition) {
            return $criteria->addFilter(new EqualsFilter('product.id', $resolverContext->getEntity()->get('id')));
        }

        $productConfig = $slot->getFieldConfig()->get('product');
        if ($productConfig === null || !$productConfig->isStatic() || $productConfig->getValue() === null) {
            return null;
        }

        // the best variant is only known in `enrich`, so the documents of all variants are loaded
        return $criteria->addFilter(new OrFilter([
            new EqualsFilter('product.id', $productConfig->getStringValue()),
            new EqualsFilter('product.parentId', $productConfig->getStringValue()),
        ]));
    }

    /**
     * @param EntityCollection<covariant Entity>|null $documents
     */
    private function addDocuments(ProductDescriptionReviewsStruct $data, SalesChannelProductEntity $product, ?EntityCollection $documents, Context $context): void
    {
        if (!$documents instanceof ProductDocumentCollection) {
            return;
        }

        $ownDocuments = $documents->filterByProperty('productId', $product->getId());
        $parentId = $product->getParentId();
        $documents = $ownDocuments->count() === 0 && $parentId !== null
            ? $documents->filterByProperty('productId', $parentId)
            : $ownDocuments;

        $mediaIds = array_filter(
            $documents->map(static fn (ProductDocumentEntity $document): mixed => $document->getTranslation('mediaId')),
            \is_string(...)
        );

        if ($mediaIds === []) {
            return;
        }

        $mediaCriteria = new Criteria(array_values(array_unique($mediaIds)));
        $mediaCriteria->setTitle('cms::product-description-reviews-document-media');

        $data->setDocuments($documents);
        $data->setDocumentMedia($this->mediaRepository->search($mediaCriteria, $context)->getEntities());
    }
}
