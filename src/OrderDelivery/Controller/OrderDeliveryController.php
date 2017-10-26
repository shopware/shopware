<?php declare(strict_types=1);

namespace Shopware\OrderDelivery\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\OrderDelivery\Repository\OrderDeliveryRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.order_delivery.api_controller", path="/api")
 */
class OrderDeliveryController extends ApiController
{
    /**
     * @var OrderDeliveryRepository
     */
    private $orderDeliveryRepository;

    public function __construct(OrderDeliveryRepository $orderDeliveryRepository)
    {
        $this->orderDeliveryRepository = $orderDeliveryRepository;
    }

    /**
     * @Route("/orderDelivery.{responseFormat}", name="api.orderDelivery.list", methods={"GET"})
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

        $orderDeliveries = $this->orderDeliveryRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $orderDeliveries, 'total' => $orderDeliveries->getTotal()],
            $context
        );
    }

    /**
     * @Route("/orderDelivery/{orderDeliveryUuid}.{responseFormat}", name="api.orderDelivery.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('orderDeliveryUuid');
        $orderDeliveries = $this->orderDeliveryRepository->readDetail(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(['data' => $orderDeliveries->get($uuid)], $context);
    }

    /**
     * @Route("/orderDelivery.{responseFormat}", name="api.orderDelivery.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->orderDeliveryRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $orderDeliveries = $this->orderDeliveryRepository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $orderDeliveries,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/orderDelivery.{responseFormat}", name="api.orderDelivery.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->orderDeliveryRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $orderDeliveries = $this->orderDeliveryRepository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $orderDeliveries,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/orderDelivery.{responseFormat}", name="api.orderDelivery.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->orderDeliveryRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $orderDeliveries = $this->orderDeliveryRepository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $orderDeliveries,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/orderDelivery/{orderDeliveryUuid}.{responseFormat}", name="api.orderDelivery.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('orderDeliveryUuid');

        $updateEvent = $this->orderDeliveryRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $orderDeliveries = $this->orderDeliveryRepository->readDetail(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $orderDeliveries->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/orderDelivery.{responseFormat}", name="api.orderDelivery.delete", methods={"DELETE"})
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
        return 'orderDeliveries';
    }

    protected function getXmlChildKey(): string
    {
        return 'orderDelivery';
    }
}
