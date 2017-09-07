<?php declare(strict_types=1);

namespace Shopware\Product\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Api\ResultFormat;
use Shopware\Product\ProductRepository;
use Shopware\Search\Criteria;
use Symfony\Component\HttpFoundation\JsonResponse;
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
     * @Route("/product.{responseFormat}", name="api.product.list", methods={"GET", "OPTIONS"})
     */
    public function listAction(ApiContext $context): Response
    {
        $criteria = new Criteria();

        $searchResult = $this->productRepository->search($criteria, $context->getShopContext()->getTranslationContext());

        switch ($context->getResultFormat()) {
            case ResultFormat::BASIC:
                $products = $this->productRepository->read($searchResult->getUuids(), $context->getShopContext()->getTranslationContext());
                break;
            case ResultFormat::BASIC_NEXUS:
                $products = $this->productBackendRepository->readBasic($searchResult->getUuids(), $context->getShopContext());
                break;
            default:
                throw new \Exception("Result format not supported.");
        }

        return $this->createResponse($products, $context);
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
        $result = [];
        foreach ($context->getPayload() as $product) {
//            $result[] = $this->productRepository->create($product);
        }

        return $this->createResponse($result, $context);
    }

    /**
     * @Route("/product/{productUuid}.{responseFormat}", name="api.product.update", methods={"PUT"})
     */
    public function updateAction(Request $request, ApiContext $context): Response
    {
        $result = [];

        // todo: update data
//        foreach ($context->getPayload() as $product) {
//            // todo check data types from xml
//            $product['lastStock'] = (int) $product['lastStock'];
//            $product['crossbundlelook'] = (int) $product['crossbundlelook'];
//            $product['notification'] = (int) $product['notification'];
//            $product['mode'] = (int) $product['mode'];
//
//            $result[] = $this->productRepository->update($product);
//        }

        return $this->detailAction($request, $context);
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
