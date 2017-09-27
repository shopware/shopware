<?php declare(strict_types=1);

namespace Shopware\OrderState\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\OrderState\Repository\OrderStateRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.order_state.api_controller", path="/api")
 */
class OrderStateController extends ApiController
{
    /**
     * @var OrderStateRepository
     */
    private $orderStateRepository;

    public function __construct(OrderStateRepository $orderStateRepository)
    {
        $this->orderStateRepository = $orderStateRepository;
    }

    public function getXmlRootKey(): string
    {
        return 'orderStates';
    }

    public function getXmlChildKey(): string
    {
        return 'orderState';
    }

    /**
     * @Route("/orderState.{responseFormat}", name="api.orderState.list", methods={"GET"})
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

        $orderStates = $this->orderStateRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $orderStates, 'total' => $orderStates->getTotal()],
            $context
        );
    }

    /**
     * @Route("/orderState/{orderStateUuid}.{responseFormat}", name="api.orderState.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('orderStateUuid');
        $orderStates = $this->orderStateRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(['data' => $orderStates->get($uuid)], $context);
    }

    /**
     * @Route("/orderState.{responseFormat}", name="api.orderState.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->orderStateRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $orderStates = $this->orderStateRepository->read(
            $createEvent->getOrderStateUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $orderStates,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/orderState.{responseFormat}", name="api.orderState.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->orderStateRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $orderStates = $this->orderStateRepository->read(
            $createEvent->getOrderStateUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $orderStates,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/orderState.{responseFormat}", name="api.orderState.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->orderStateRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $orderStates = $this->orderStateRepository->read(
            $createEvent->getOrderStateUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $orderStates,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/orderState/{orderStateUuid}.{responseFormat}", name="api.orderState.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('orderStateUuid');

        $updateEvent = $this->orderStateRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $orderStates = $this->orderStateRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $orderStates->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/orderState.{responseFormat}", name="api.orderState.delete", methods={"DELETE"})
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
}
