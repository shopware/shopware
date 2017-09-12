<?php declare(strict_types=1);

namespace Shopware\Product\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Api\ResultFormat;
use Shopware\Product\ProductRepository;
use Shopware\Search\Criteria;
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
    public function listAction(Request $request, ApiContext $context): Response
    {
        $criteria = new Criteria();

        if ($request->query->has('offset')) {
            $criteria->offset($request->query->get('offset'));
        }

        if ($request->query->has('limit')) {
            $criteria->limit($request->query->get('limit'));
        }

        $criteria->setFetchCount(true);

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
        $result = [];
        foreach ($context->getPayload() as $product) {
            $result[] = $this->productRepository->create($product, $context->getShopContext()->getTranslationContext());
        }

        return $this->createResponse($result, $context);
    }

    /**
     * @Route("/product/{productUuid}.{responseFormat}", name="api.product.update", methods={"PUT"})
     */
    public function updateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('productUuid');

        try {
            $this->productRepository->update($payload, $context->getShopContext()->getTranslationContext());
        } catch (\Exception $ex) {
            
        }

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
