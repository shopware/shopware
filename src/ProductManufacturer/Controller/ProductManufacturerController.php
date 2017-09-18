<?php declare(strict_types=1);

namespace Shopware\ProductManufacturer\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Api\ResultFormat;
use Shopware\ProductManufacturer\Repository\ProductManufacturerRepository;
use Shopware\Search\Criteria;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.product_manufacturer.controller.product_manufacturer_controller", path="/api")
 */
class ProductManufacturerController extends ApiController
{
    public function getXmlRootKey(): string
    {
        return 'productManufacturers';
    }

    public function getXmlChildKey(): string
    {
        return 'productManufacturer';
    }

    /**
     * @var ProductManufacturerRepository
     */
    private $productManufacturerRepository;

    public function __construct(ProductManufacturerRepository $productManufacturerRepository)
    {
        $this->productManufacturerRepository = $productManufacturerRepository;
    }

    /**
     * @Route("/productManufacturer.{responseFormat}", name="api.product_manufacturer.list", methods={"GET"})
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

        $criteria->setFetchCount(true);

        $searchResult = $this->productManufacturerRepository->search($criteria, $context->getShopContext()->getTranslationContext());

        switch ($context->getResultFormat()) {
            case ResultFormat::BASIC:
                $manufacturers = $this->productManufacturerRepository->read($searchResult->getUuids(), $context->getShopContext()->getTranslationContext());
                break;
            default:
                throw new \Exception("Result format not supported.");
        }

        $response = [
            'data' => $manufacturers,
            'total' => $searchResult->getTotal()
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productManufacturer/{manufacturerUuid}.{responseFormat}", name="api.product_manufacturer.detail", methods={"GET"})
     */
    public function detailAction(Request $request, ApiContext $context)
    {
        $uuid = $request->get('manufacturerUuid');

        $manufacturers = $this->productManufacturerRepository->read([$uuid], $context->getShopContext()->getTranslationContext());
        $manufacturer = $manufacturers->get($uuid);

        return $this->createResponse($manufacturer, $context);
    }
}
