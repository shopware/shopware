<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Garan;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Garan\GaranLabelDurationFormatter;
use Shopware\Core\Content\Product\Garan\GaranLabelRenderer;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
class GaranLabelRoute extends AbstractGaranLabelRoute
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $productRepository,
        private readonly GaranLabelDurationFormatter $durationFormatter,
        private readonly GaranLabelRenderer $renderer,
    ) {
    }

    public function getDecorated(): AbstractGaranLabelRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/product/{productId}/garan-label',
        name: 'store-api.product.garan-label',
        methods: [Request::METHOD_GET]
    )]
    public function load(string $productId, SalesChannelContext $context): GaranLabelRouteResponse
    {
        $product = $this->loadProduct($productId, $context);
        $brand = trim((string) $product->getManufacturer()?->getName());
        $modelIdentifier = trim($product->getManufacturerNumber() ?? $product->getProductNumber());
        $duration = $this->durationFormatter->formatMonths($product->getGuaranteeMonths());

        if ($brand === '' || $modelIdentifier === '' || $duration === null) {
            return new GaranLabelRouteResponse(null);
        }

        return new GaranLabelRouteResponse($this->renderer->render($duration, $brand, $modelIdentifier));
    }

    private function loadProduct(string $productId, SalesChannelContext $context): SalesChannelProductEntity
    {
        $criteria = new Criteria([$productId]);
        $criteria->setTitle('product-garan-label-route');
        $criteria->addAssociation('manufacturer');
        $criteria->addFilter(new ProductAvailableFilter($context->getSalesChannelId(), ProductVisibilityDefinition::VISIBILITY_LINK));

        $product = $this->productRepository->search($criteria, $context)->getEntities()->first();

        if (!$product instanceof SalesChannelProductEntity) {
            throw ProductException::productNotFound($productId);
        }

        return $product;
    }
}
