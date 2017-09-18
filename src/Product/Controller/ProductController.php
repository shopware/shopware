<?php declare(strict_types=1);

namespace Shopware\Product\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Api\ResultFormat;
use Shopware\Product\Repository\ProductRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.product.controller.product_controller", path="/api")
 */
class ProductController extends ApiController
{
    public function getXmlRootKey(): string
    {
        return 'products';
    }

    public function getXmlChildKey(): string
    {
        return 'product';
    }

    /**
     * @var ProductRepository
     */
    private $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    /**
     * @Route("/product.{responseFormat}", name="api.product.list", methods={"GET"})
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
            $parser = new QueryStringParser();
            $criteria->addFilter(
                $parser->fromUrl($request->query->get('query'))
            );
        }

        $criteria->setFetchCount(true);

        $searchResult = $this->productRepository->searchUuids($criteria, $context->getShopContext()->getTranslationContext());

        switch ($context->getResultFormat()) {
            case ResultFormat::BASIC:
                $products = $this->productRepository->read($searchResult->getUuids(), $context->getShopContext()->getTranslationContext());
                break;
//            case ResultFormat::BASIC_NEXUS:
//                $products = $this->productBackendRepository->readBasic($searchResult->getUuids(), $context->getShopContext());
//                break;
            default:
                throw new \Exception('Result format not supported.');
        }

        $response = [
            'data' => $products,
            'total' => $searchResult->getTotal()
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/product/{productUuid}.{responseFormat}", name="api.product.detail", methods={"GET"})
     */
    public function detailAction(Request $request, ApiContext $context)
    {
        $uuid = $request->get('productUuid');

        $products = $this->productRepository->read([$uuid], $context->getShopContext()->getTranslationContext());
        $product = $products->get($uuid);

        return $this->createResponse($product, $context);
    }

    /**
     * @Route("/product.{responseFormat}", name="api.product.create", methods={"POST"})
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->productRepository->create($context->getPayload(), $context->getShopContext()->getTranslationContext());

        $response = [
            'data' => $this->productRepository->read($createEvent->getProductUuids(), $context->getShopContext()->getTranslationContext()),
            'errors' => $createEvent->getErrors()
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/product.{responseFormat}", name="api.product.upsert", methods={"PUT"})
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->productRepository->upsert($context->getPayload(), $context->getShopContext()->getTranslationContext());

        $response = [
            'data' => $this->productRepository->read($createEvent->getProductUuids(), $context->getShopContext()->getTranslationContext()),
            'errors' => $createEvent->getErrors()
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/product.{responseFormat}", name="api.product.update", methods={"PATCH"})
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->productRepository->update($context->getPayload(), $context->getShopContext()->getTranslationContext());

        $response = [
            'data' => $this->productRepository->read($createEvent->getProductUuids(), $context->getShopContext()->getTranslationContext()),
            'errors' => $createEvent->getErrors()
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/product/{productUuid}.{responseFormat}", name="api.product.single_update", methods={"PATCH"})
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('productUuid');

        $updateEvent = $this->productRepository->update([$payload], $context->getShopContext()->getTranslationContext());

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        return $this->createResponse(
            ['data' => $this->productRepository->read([$payload['uuid']], $context->getShopContext()->getTranslationContext())->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/product.{responseFormat}", name="api.product.delete", methods={"DELETE"})
     */
    public function deleteAction(ApiContext $context): Response
    {
        $result = [];
        foreach ($context->getPayload() as $product) {
            // delete product
//            $result[] = $this->productRepository->delete($product);
        }

        return $this->createResponse($result, $context);
    }
}
