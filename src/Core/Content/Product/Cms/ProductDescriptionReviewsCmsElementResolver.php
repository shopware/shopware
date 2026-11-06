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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[Package('discovery')]
class ProductDescriptionReviewsCmsElementResolver extends AbstractProductDetailCmsElementResolver
{
    final public const TYPE = 'product-description-reviews';

    private const DOCUMENTS_KEY = 'product_documents_';

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

        if (!$resolverContext instanceof EntityResolverContext || !$resolverContext->getDefinition() instanceof SalesChannelProductDefinition) {
            return $criteriaCollection;
        }

        // `product.id` resolves the documents a variant inherits from its parent
        $criteria = new Criteria();
        $criteria->setTitle('cms::product-description-reviews-documents');
        $criteria->addFilter(new EqualsFilter('product.id', $resolverContext->getEntity()->getUniqueIdentifier()));
        $criteria->addSorting(new FieldSorting('position'));

        $criteriaCollection ??= new CriteriaCollection();
        $criteriaCollection->add(self::DOCUMENTS_KEY . $slot->getUniqueIdentifier(), ProductDocumentDefinition::class, $criteria);

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

        $documents = $result->get(self::DOCUMENTS_KEY . $slot->getUniqueIdentifier())?->getEntities();
        if ($documents instanceof ProductDocumentCollection) {
            $this->addDocuments($data, $documents, $resolverContext->getSalesChannelContext()->getContext());
        }

        if ($this->systemConfigService->getBool('core.listing.showReview', $resolverContext->getSalesChannelContext()->getSalesChannelId())) {
            $reviews = $this->productReviewLoader->load($request, $resolverContext->getSalesChannelContext(), $product->getId(), $product->getParentId());

            $this->scriptExecutor->execute(new ProductReviewsWidgetLoadedHook($reviews, $resolverContext->getSalesChannelContext()));

            $data->setReviews($reviews);
        }
    }

    private function addDocuments(ProductDescriptionReviewsStruct $data, ProductDocumentCollection $documents, Context $context): void
    {
        $mediaIds = array_filter(
            $documents->map(static fn (ProductDocumentEntity $document): mixed => $document->getTranslation('mediaId')),
            \is_string(...)
        );

        if ($mediaIds === []) {
            return;
        }

        $mediaCriteria = new Criteria(array_values(array_unique($mediaIds)));
        $mediaCriteria->setTitle('cms::product-description-reviews-document-media');
        // the slot data is exposed by the Store API, which must not leak the path of private media
        $mediaCriteria->addFilter(new EqualsFilter('private', false));

        $media = $this->mediaRepository->search($mediaCriteria, $context)->getEntities();

        $data->setDocuments($documents->filter(
            static fn (ProductDocumentEntity $document): bool => $media->has($mediaIds[$document->getId()] ?? '')
        ));
        $data->setDocumentMedia($media);
    }
}
