<?php declare(strict_types=1);

namespace Shopware\OrderLineItem\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\OrderLineItem\Repository\OrderLineItemRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.order_line_item.api_controller", path="/api")
 */
class OrderLineItemController extends ApiController
{
    /**
     * @var OrderLineItemRepository
     */
    private $orderLineItemRepository;

    public function __construct(OrderLineItemRepository $orderLineItemRepository)
    {
        $this->orderLineItemRepository = $orderLineItemRepository;
    }

    /**
     * @Route("/orderLineItem.{responseFormat}", name="api.orderLineItem.list", methods={"GET"})
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

        $orderLineItems = $this->orderLineItemRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $orderLineItems, 'total' => $orderLineItems->getTotal()],
            $context
        );
    }

    /**
     * @Route("/orderLineItem/{orderLineItemUuid}.{responseFormat}", name="api.orderLineItem.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('orderLineItemUuid');
        $orderLineItems = $this->orderLineItemRepository->readBasic(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(['data' => $orderLineItems->get($uuid)], $context);
    }

    /**
     * @Route("/orderLineItem.{responseFormat}", name="api.orderLineItem.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->orderLineItemRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $orderLineItems = $this->orderLineItemRepository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $orderLineItems,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/orderLineItem.{responseFormat}", name="api.orderLineItem.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->orderLineItemRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $orderLineItems = $this->orderLineItemRepository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $orderLineItems,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/orderLineItem.{responseFormat}", name="api.orderLineItem.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->orderLineItemRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $orderLineItems = $this->orderLineItemRepository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $orderLineItems,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/orderLineItem/{orderLineItemUuid}.{responseFormat}", name="api.orderLineItem.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('orderLineItemUuid');

        $updateEvent = $this->orderLineItemRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $orderLineItems = $this->orderLineItemRepository->readBasic(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $orderLineItems->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/orderLineItem.{responseFormat}", name="api.orderLineItem.delete", methods={"DELETE"})
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
        return 'orderLineItems';
    }

    protected function getXmlChildKey(): string
    {
        return 'orderLineItem';
    }
}
