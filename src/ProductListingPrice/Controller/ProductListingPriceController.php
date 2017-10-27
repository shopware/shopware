<?php declare(strict_types=1);

namespace Shopware\ProductListingPrice\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\Parser\QueryStringParser;
use Shopware\ProductListingPrice\Repository\ProductListingPriceRepository;
use Shopware\Rest\ApiContext;
use Shopware\Rest\ApiController;
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
        $productListingPrices = $this->productListingPriceRepository->readBasic(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(['data' => $productListingPrices->get($uuid)], $context);
    }

    /**
     * @Route("/productListingPrice.{responseFormat}", name="api.productListingPrice.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->productListingPriceRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productListingPrices = $this->productListingPriceRepository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productListingPrices,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productListingPrice.{responseFormat}", name="api.productListingPrice.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->productListingPriceRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productListingPrices = $this->productListingPriceRepository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productListingPrices,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productListingPrice.{responseFormat}", name="api.productListingPrice.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->productListingPriceRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productListingPrices = $this->productListingPriceRepository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productListingPrices,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productListingPrice/{productListingPriceUuid}.{responseFormat}", name="api.productListingPrice.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('productListingPriceUuid');

        $updateEvent = $this->productListingPriceRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $productListingPrices = $this->productListingPriceRepository->readBasic(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $productListingPrices->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/productListingPrice.{responseFormat}", name="api.productListingPrice.delete", methods={"DELETE"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function deleteAction(ApiContext $context): Response
    {
        $result = ['data' => []];

        return $this->createResponse($result, $context);
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
