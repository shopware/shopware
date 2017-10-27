<?php declare(strict_types=1);

namespace Shopware\OrderDeliveryPosition\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\Parser\QueryStringParser;
use Shopware\OrderDeliveryPosition\Repository\OrderDeliveryPositionRepository;
use Shopware\Rest\ApiContext;
use Shopware\Rest\ApiController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.order_delivery_position.api_controller", path="/api")
 */
class OrderDeliveryPositionController extends ApiController
{
    /**
     * @var OrderDeliveryPositionRepository
     */
    private $orderDeliveryPositionRepository;

    public function __construct(OrderDeliveryPositionRepository $orderDeliveryPositionRepository)
    {
        $this->orderDeliveryPositionRepository = $orderDeliveryPositionRepository;
    }

    /**
     * @Route("/orderDeliveryPosition.{responseFormat}", name="api.orderDeliveryPosition.list", methods={"GET"})
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

        $orderDeliveryPositions = $this->orderDeliveryPositionRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $orderDeliveryPositions, 'total' => $orderDeliveryPositions->getTotal()],
            $context
        );
    }

    /**
     * @Route("/orderDeliveryPosition/{orderDeliveryPositionUuid}.{responseFormat}", name="api.orderDeliveryPosition.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('orderDeliveryPositionUuid');
        $orderDeliveryPositions = $this->orderDeliveryPositionRepository->readBasic(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(['data' => $orderDeliveryPositions->get($uuid)], $context);
    }

    /**
     * @Route("/orderDeliveryPosition.{responseFormat}", name="api.orderDeliveryPosition.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->orderDeliveryPositionRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $orderDeliveryPositions = $this->orderDeliveryPositionRepository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $orderDeliveryPositions,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/orderDeliveryPosition.{responseFormat}", name="api.orderDeliveryPosition.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->orderDeliveryPositionRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $orderDeliveryPositions = $this->orderDeliveryPositionRepository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $orderDeliveryPositions,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/orderDeliveryPosition.{responseFormat}", name="api.orderDeliveryPosition.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->orderDeliveryPositionRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $orderDeliveryPositions = $this->orderDeliveryPositionRepository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $orderDeliveryPositions,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/orderDeliveryPosition/{orderDeliveryPositionUuid}.{responseFormat}", name="api.orderDeliveryPosition.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('orderDeliveryPositionUuid');

        $updateEvent = $this->orderDeliveryPositionRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $orderDeliveryPositions = $this->orderDeliveryPositionRepository->readBasic(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $orderDeliveryPositions->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/orderDeliveryPosition.{responseFormat}", name="api.orderDeliveryPosition.delete", methods={"DELETE"})
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
        return 'orderDeliveryPositions';
    }

    protected function getXmlChildKey(): string
    {
        return 'orderDeliveryPosition';
    }
}
