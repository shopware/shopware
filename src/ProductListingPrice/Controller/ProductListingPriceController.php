<?php declare(strict_types=1);

namespace Shopware\ProductListingPrice\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\ProductListingPrice\Repository\ProductListingPriceRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.product_listing_price_ro.api_controller", path="/api")
 */
class ProductListingPriceController extends ApiController
{
    /**
     * @var ProductListingPriceRepository
     */
    private $productListingPriceRepository;

    public function __construct(ProductListingPriceRepository $productListingPriceRepository)
    {
        $this->productListingPriceRepository = $productListingPriceRepository;
    }

    /**
     * @Route("/productListingPrice.{responseFormat}", name="api.productListingPrice.list", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function listAction(Request $request, ApiContext $context): Response
    {
        $criteria = new Criteria();

        if ($request->query->has('offset')) {
            $criteria->setOffset((int) $request->query->get('offset'));
        }

        if ($request->query->has('limit')) {
            $criteria->setLimit((int) $request->query->get('limit'));
        }

        if ($request->query->has('query')) {
            $criteria->addFilter(
                QueryStringParser::fromUrl($request->query->get('query'))
            );
        }

        $criteria->setFetchCount(true);

        $productListingPrices = $this->productListingPriceRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $productListingPrices, 'total' => $productListingPrices->getTotal()],
            $context
        );
    }

    /**
     * @Route("/productListingPrice/{productListingPriceUuid}.{responseFormat}", name="api.productListingPrice.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('productListingPriceUuid');
        $productListingPrices = $this->productListingPriceRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(['data' => $productListingPrices->get($uuid)], $context);
    }

    protected function getXmlRootKey(): string
    {
        return 'productListingPrices';
    }

    protected function getXmlChildKey(): string
    {
        return 'productListingPrice';
    }
}
