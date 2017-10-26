<?php declare(strict_types=1);

namespace Shopware\ProductDetailPrice\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\ProductDetailPrice\Repository\ProductDetailPriceRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.product_detail_price.api_controller", path="/api")
 */
class ProductDetailPriceController extends ApiController
{
    /**
     * @var ProductDetailPriceRepository
     */
    private $productDetailPriceRepository;

    public function __construct(ProductDetailPriceRepository $productDetailPriceRepository)
    {
        $this->productDetailPriceRepository = $productDetailPriceRepository;
    }

    /**
     * @Route("/productDetailPrice.{responseFormat}", name="api.productDetailPrice.list", methods={"GET"})
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

        $productDetailPrices = $this->productDetailPriceRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $productDetailPrices, 'total' => $productDetailPrices->getTotal()],
            $context
        );
    }

    /**
     * @Route("/productDetailPrice/{productDetailPriceUuid}.{responseFormat}", name="api.productDetailPrice.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('productDetailPriceUuid');
        $productDetailPrices = $this->productDetailPriceRepository->readBasic(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(['data' => $productDetailPrices->get($uuid)], $context);
    }

    /**
     * @Route("/productDetailPrice.{responseFormat}", name="api.productDetailPrice.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->productDetailPriceRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productDetailPrices = $this->productDetailPriceRepository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productDetailPrices,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productDetailPrice.{responseFormat}", name="api.productDetailPrice.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->productDetailPriceRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productDetailPrices = $this->productDetailPriceRepository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productDetailPrices,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productDetailPrice.{responseFormat}", name="api.productDetailPrice.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->productDetailPriceRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productDetailPrices = $this->productDetailPriceRepository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productDetailPrices,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productDetailPrice/{productDetailPriceUuid}.{responseFormat}", name="api.productDetailPrice.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('productDetailPriceUuid');

        $updateEvent = $this->productDetailPriceRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $productDetailPrices = $this->productDetailPriceRepository->readBasic(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $productDetailPrices->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/productDetailPrice.{responseFormat}", name="api.productDetailPrice.delete", methods={"DELETE"})
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
        return 'productDetailPrices';
    }

    protected function getXmlChildKey(): string
    {
        return 'productDetailPrice';
    }
}
