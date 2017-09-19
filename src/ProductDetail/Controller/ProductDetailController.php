<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Api\ResultFormat;
use Shopware\ProductDetail\Repository\ProductDetailRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.product_detail.api_controller", path="/api")
 */
class ProductDetailController extends ApiController
{
    /**
     * @var ProductDetailRepository
     */
    private $productDetailRepository;

    public function __construct(ProductDetailRepository $productDetailRepository)
    {
        $this->productDetailRepository = $productDetailRepository;
    }

    public function getXmlRootKey(): string
    {
        return 'productDetails';
    }

    public function getXmlChildKey(): string
    {
        return 'productDetail';
    }

    /**
     * @Route("/productDetail.{responseFormat}", name="api.productDetail.list", methods={"GET"})
     * @param Request $request
     * @param ApiContext $context
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

        $searchResult = $this->productDetailRepository->searchUuids(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        switch ($context->getResultFormat()) {
            case ResultFormat::BASIC:
                $productDetails = $this->productDetailRepository->read(
                    $searchResult->getUuids(),
                    $context->getShopContext()->getTranslationContext()
                );
                break;
            default:
                throw new \RuntimeException('Result format not supported.');
        }

        $response = [
            'data' => $productDetails,
            'total' => $searchResult->getTotal(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productDetail/{productDetailUuid}.{responseFormat}", name="api.productDetail.detail", methods={"GET"})
     * @param Request $request
     * @param ApiContext $context
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('productDetailUuid');
        $productDetails = $this->productDetailRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse($productDetails->get($uuid), $context);
    }

    /**
     * @Route("/productDetail.{responseFormat}", name="api.productDetail.create", methods={"POST"})
     * @param ApiContext $context
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->productDetailRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productDetails = $this->productDetailRepository->read(
            $createEvent->getProductDetailUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productDetails,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productDetail.{responseFormat}", name="api.productDetail.upsert", methods={"PUT"})
     * @param ApiContext $context
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->productDetailRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productDetails = $this->productDetailRepository->read(
            $createEvent->getProductDetailUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productDetails,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productDetail.{responseFormat}", name="api.productDetail.update", methods={"PATCH"})
     * @param ApiContext $context
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->productDetailRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productDetails = $this->productDetailRepository->read(
            $createEvent->getProductDetailUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productDetails,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productDetail/{productDetailUuid}.{responseFormat}", name="api.productDetail.single_update", methods={"PATCH"})
     * @param Request $request
     * @param ApiContext $context
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('productDetailUuid');

        $updateEvent = $this->productDetailRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $productDetails = $this->productDetailRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $productDetails->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/productDetail.{responseFormat}", name="api.productDetail.delete", methods={"DELETE"})
     * @param ApiContext $context
     * @return Response
     */
    public function deleteAction(ApiContext $context): Response
    {
        $result = [];

        return $this->createResponse($result, $context);
    }
}
