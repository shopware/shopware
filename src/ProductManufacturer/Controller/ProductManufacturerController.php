<?php declare(strict_types=1);

namespace Shopware\ProductManufacturer\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\ProductManufacturer\Repository\ProductManufacturerRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.product_manufacturer.api_controller", path="/api")
 */
class ProductManufacturerController extends ApiController
{
    /**
     * @var ProductManufacturerRepository
     */
    private $productManufacturerRepository;

    public function __construct(ProductManufacturerRepository $productManufacturerRepository)
    {
        $this->productManufacturerRepository = $productManufacturerRepository;
    }

    public function getXmlRootKey(): string
    {
        return 'productManufacturers';
    }

    public function getXmlChildKey(): string
    {
        return 'productManufacturer';
    }

    /**
     * @Route("/productManufacturer.{responseFormat}", name="api.productManufacturer.list", methods={"GET"})
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

        $productManufacturers = $this->productManufacturerRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productManufacturers->getElements(),
            'total' => $productManufacturers->getTotal(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productManufacturer/{productManufacturerUuid}.{responseFormat}", name="api.productManufacturer.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('productManufacturerUuid');
        $productManufacturers = $this->productManufacturerRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse($productManufacturers->get($uuid), $context);
    }

    /**
     * @Route("/productManufacturer.{responseFormat}", name="api.productManufacturer.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->productManufacturerRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productManufacturers = $this->productManufacturerRepository->read(
            $createEvent->getProductManufacturerUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productManufacturers,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productManufacturer.{responseFormat}", name="api.productManufacturer.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->productManufacturerRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productManufacturers = $this->productManufacturerRepository->read(
            $createEvent->getProductManufacturerUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productManufacturers,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productManufacturer.{responseFormat}", name="api.productManufacturer.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->productManufacturerRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productManufacturers = $this->productManufacturerRepository->read(
            $createEvent->getProductManufacturerUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productManufacturers,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productManufacturer/{productManufacturerUuid}.{responseFormat}", name="api.productManufacturer.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('productManufacturerUuid');

        $updateEvent = $this->productManufacturerRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $productManufacturers = $this->productManufacturerRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $productManufacturers->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/productManufacturer.{responseFormat}", name="api.productManufacturer.delete", methods={"DELETE"})
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
