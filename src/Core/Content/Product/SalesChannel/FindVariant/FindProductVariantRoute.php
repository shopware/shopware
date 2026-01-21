<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\FindVariant;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('inventory')]
class FindProductVariantRoute extends AbstractFindProductVariantRoute
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $productRepository,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    public function getDecorated(): AbstractFindProductVariantRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/product/{productId}/find-variant',
        name: 'store-api.product.find-variant',
        methods: [Request::METHOD_POST, Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => ProductDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]
    )]
    public function load(string $productId, Request $request, SalesChannelContext $context): FindProductVariantRouteResponse
    {
        $switchedGroup = RequestParamHelper::get($request, 'switchedGroup');

        $options = RequestParamHelper::get($request, 'options', []);

        foreach ($options as $optionId) {
            if (!\is_string($optionId)) {
                throw ProductException::invalidOptionsParameter();
            }
        }

        if (Feature::isActive('v6.8.0.0') || Feature::isActive('CACHE_REWORK')) {
            $this->cacheTagCollector->addTag(EntityCacheKeyGenerator::buildProductTag($productId));
        }

        $variantId = $this->searchForOptions($productId, $context, $options);

        if ($variantId !== null) {
            return new FindProductVariantRouteResponse(new FoundCombination($variantId, $options));
        }

        while (\count($options) > 1) {
            foreach ($options as $groupId => $_optionId) {
                if ($groupId !== $switchedGroup) {
                    unset($options[$groupId]);

                    break;
                }
            }

            $variantId = $this->searchForOptions($productId, $context, $options);

            if ($variantId) {
                return new FindProductVariantRouteResponse(new FoundCombination($variantId, $options));
            }
        }

        throw ProductException::variantNotFound($productId, $options);
    }

    /**
     * @param array<string> $options
     */
    private function searchForOptions(
        string $productId,
        SalesChannelContext $salesChannelContext,
        array $options
    ): ?string {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('product.parentId', $productId))
            ->setLimit(1);

        foreach ($options as $optionId) {
            $criteria->addFilter(new EqualsFilter('product.optionIds', $optionId));
        }

        return $this->productRepository->searchIds($criteria, $salesChannelContext)->firstId();
    }
}
