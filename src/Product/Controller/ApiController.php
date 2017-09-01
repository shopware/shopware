<?php declare(strict_types=1);

namespace Shopware\Product\Controller;

use Shopware\Category\Gateway\CategoryDenormalization;
use Shopware\Framework\Api2\Resource\ResourceRegistry;
use Shopware\Product\Gateway\ProductRepository;
use Shopware\Product\Gateway\Resource\ProductResource;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiController
{
    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var CategoryDenormalization
     */
    private $categoryDenormalization;

    /**
     * @var ResourceRegistry
     */
    private $resourceRegistry;

    /**
     * @param ProductRepository $productRepository
     */
    public function __construct(
        ProductRepository $productRepository,
        CategoryDenormalization $categoryDenormalization,
        ResourceRegistry $resourceRegistry
    )
    {
        $this->productRepository = $productRepository;
        $this->categoryDenormalization = $categoryDenormalization;
        $this->resourceRegistry = $resourceRegistry;
    }

    public function listAction(Request $request, ApiContext $context): Response
    {
        return $this->createResponse([[
            'id' => 1,
            'uuid' => 'c948c7cc-9143-11e7-abc4-cec278b6b50a',
            'productManufacturerUuid' => 'd2158bce-9143-11e7-abc4-cec278b6b50a',
            'name' => 'Spachtelmasse',
            'description' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat',
            'descriptionLong' => '<p>Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat</p><p>Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat</p>',
            'shippingTime' => new \DateTime(),
            'createdAt' => new \DateTime(),
            'active' => true,
            'taxUuid' => '331f8820-9144-11e7-abc4-cec278b6b50a',
            'productDetailUuid' => '3c781644-9144-11e7-abc4-cec278b6b50a',
            'pseudoSales' => 42,
            'topseller' => false,
            'metaTitle' => 'Spachtelmasse Meta Titel',
            'keywords' => 'a, couple, keywords',
            'updatedAt' => new \DateTime(),
            'priceGroupId' => 1,
            'filterGroupUuid' => '6b54d254-9144-11e7-abc4-cec278b6b50a',
            'lastStock' => false,
            'crossbundlelook' =>  false,
            'notification' => false,
            'template' => 'default',
            'mode' => 1,
            'availableFrom' => null,
            'availableTo' => null,
            'configuratorSetId' => null,
            'productManufacturer' => [
                'id' => 1,
                'uuid' => 'd2158bce-9143-11e7-abc4-cec278b6b50a',
                'name' => 'shopware AG',
                'img' => null,
                'link' => 'https://de.shopware.com',
                'description' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat',
                'metaTitle' => 'Hersteller "shopware AG"',
                'metaDescription' => null,
                'metaKeywords' => null,
                'updatedAt' => new \DateTime()
            ],
            'mainDetail' => [
                'id' => 1,
                'uuid' => '3c781644-9144-11e7-abc4-cec278b6b50a',
                'productId' => 2,
                'productUuid' => 'e670707e-9144-11e7-abc4-cec278b6b50a',
                'orderNumber' => 'SWX10000',
                'supplierNumber' => 'SW-SPACHTELMASSE-1',
                'kind' => 0,
                'additionalText' => null,
                'sales' => 42,
                'active' => true,
                'stock' => 120,
                'stockmin' => null,
                'weight' => null,
                'position' => 1,
                'width' => null,
                'height' => null,
                'length' => null,
                'ean' => null,
                'unitId' => null,
                'purchaseSteps' => 1,
                'maxPurchase' => null,
                'purchaseUnit' => null,
                'referenceUnit' => null,
                'packUnit' => null,
                'releaseDate' => new \DateTime(),
                'shippingFree' => true,
                'shippingTime' => null,
                'purchasePrice' => 199.98
            ]
        ],
        [
            'id' => 2,
            'uuid' => '2d1fffc0-9146-11e7-abc4-cec278b6b50a',
            'productManufacturerUuid' => 'd2158bce-9143-11e7-abc4-cec278b6b50a',
            'name' => 'Paradigma Snowboard',
            'description' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat',
            'descriptionLong' => '<p>Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat</p><p>Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat</p>',
            'shippingTime' => new \DateTime(),
            'createdAt' => new \DateTime(),
            'active' => true,
            'taxUuid' => '331f8820-9144-11e7-abc4-cec278b6b50a',
            'productDetailUuid' => '3c781644-9144-11e7-abc4-cec278b6b50a',
            'pseudoSales' => 42,
            'topseller' => false,
            'metaTitle' => 'Spachtelmasse Meta Titel',
            'keywords' => 'a, couple, keywords',
            'updatedAt' => new \DateTime(),
            'priceGroupId' => 1,
            'filterGroupUuid' => '6b54d254-9144-11e7-abc4-cec278b6b50a',
            'lastStock' => false,
            'crossbundlelook' =>  false,
            'notification' => false,
            'template' => 'default',
            'mode' => 1,
            'availableFrom' => null,
            'availableTo' => null,
            'configuratorSetId' => null,
            'productManufacturer' => [
                'id' => 1,
                'uuid' => 'd2158bce-9143-11e7-abc4-cec278b6b50a',
                'name' => 'shopware AG',
                'img' => null,
                'link' => 'https://de.shopware.com',
                'description' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat',
                'metaTitle' => 'Hersteller "shopware AG"',
                'metaDescription' => null,
                'metaKeywords' => null,
                'updatedAt' => new \DateTime()
            ],
            'mainDetail' => [
                'id' => 2,
                'uuid' => '2d1fffc0-9146-11e7-abc4-cec278b6b50a',
                'productId' => 3,
                'productUuid' => '373d6696-9146-11e7-abc4-cec278b6b50a',
                'orderNumber' => 'SWX10001',
                'supplierNumber' => 'SW-PRADIGMA-1',
                'kind' => 0,
                'additionalText' => null,
                'sales' => 42,
                'active' => true,
                'stock' => 120,
                'stockmin' => null,
                'weight' => null,
                'position' => 1,
                'width' => null,
                'height' => null,
                'length' => null,
                'ean' => null,
                'unitId' => null,
                'purchaseSteps' => 1,
                'maxPurchase' => null,
                'purchaseUnit' => null,
                'referenceUnit' => null,
                'packUnit' => null,
                'releaseDate' => new \DateTime(),
                'shippingFree' => true,
                'shippingTime' => null,
                'purchasePrice' => 569.00
            ]
        ]], $context);
    }

    public function detailAction(string $uuid, ApiContext $apiContext)
    {
        $result = $this->productRepository->read([$uuid], $apiContext);

        return new JsonResponse($result);
    }

    public function createAction(Request $request, ApiContext $context): Response
    {
        $result = [];
        foreach ($context->rawData as $product) {
            $result[] = $this->productRepository
                ->create($product);
        }

        return $this->createResponse($result, $context);
    }

    /**
     * @param Request $request
     * @param ApiContext $context
     * @return Response
     */
    public function updateAction(Request $request, ApiContext $context): Response
    {
        $result = [];
        foreach ($context->rawData as $product) {
            // todo check data types from xml
            $product['lastStock'] = (int) $product['lastStock'];
            $product['crossbundlelook'] = (int) $product['crossbundlelook'];
            $product['notification'] = (int) $product['notification'];
            $product['mode'] = (int) $product['mode'];

            $result[] = $this->productRepository
                ->update($product);
        }

        return $this->createResponse($result, $context);
    }

    public function deleteAction(Request $request, ApiContext $context): Response
    {
        $result = [];
        foreach ($context->rawData as $product) {
            $result[] = $this->productRepository
                ->delete($product);
        }

        return $this->createResponse($result, $context);
    }

    private function createResponse(array $result, ApiContext $context): Response
    {
        // TODO - Fix please
        $context->apiFormat = 'json';

        if ($context->apiFormat === 'json') {
            $response = new JsonResponse($result);
        } elseif ($context->apiFormat === 'xml') {
            $response = (new XmlResponse())->createResponse('products', 'product', $result);
        } else {
            return new Response('invalid api format');
        }

        // TODO -  Use paginated information
        $response->headers->set('SW-COUNT', count($result));
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET,POST,PUT');
        $response->headers->set('Access-Control-Allow-Headers', 'X-Header-One,X-Header-Two');

        return $response;
    }
}
