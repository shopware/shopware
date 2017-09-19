<?php declare(strict_types=1);

namespace Shopware\ProductPrice\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\ProductPrice\Repository\ProductPriceRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.product_price.api_controller", path="/api")
 */
class ProductPriceController extends ApiController
{
    /**
     * @var ProductPriceRepository
     */
    private $productPriceRepository;

    public function __construct(ProductPriceRepository $productPriceRepository)
    {
        $this->productPriceRepository = $productPriceRepository;
    }

    public function getXmlRootKey(): string
    {
        return 'productPrices';
    }

    public function getXmlChildKey(): string
    {
        return 'productPrice';
    }

    /**
     * @Route("/productPrice.{responseFormat}", name="api.productPrice.list", methods={"GET"})
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

        $productPrices = $this->productPriceRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productPrices,
            'total' => $productPrices->getTotal(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productPrice/{productPriceUuid}.{responseFormat}", name="api.productPrice.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('productPriceUuid');
        $productPrices = $this->productPriceRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse($productPrices->get($uuid), $context);
    }

    /**
     * @Route("/productPrice.{responseFormat}", name="api.productPrice.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->productPriceRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productPrices = $this->productPriceRepository->read(
            $createEvent->getProductPriceUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productPrices,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productPrice.{responseFormat}", name="api.productPrice.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->productPriceRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productPrices = $this->productPriceRepository->read(
            $createEvent->getProductPriceUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productPrices,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productPrice.{responseFormat}", name="api.productPrice.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->productPriceRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productPrices = $this->productPriceRepository->read(
            $createEvent->getProductPriceUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productPrices,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productPrice/{productPriceUuid}.{responseFormat}", name="api.productPrice.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('productPriceUuid');

        $updateEvent = $this->productPriceRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $productPrices = $this->productPriceRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $productPrices->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/productPrice.{responseFormat}", name="api.productPrice.delete", methods={"DELETE"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function deleteAction(ApiContext $context): Response
    {
        $result = [];

        return $this->createResponse($result, $context);
    }
}
