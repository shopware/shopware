<?php declare(strict_types=1);

namespace Shopware\OrderAddress\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\OrderAddress\Repository\OrderAddressRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.order_address.api_controller", path="/api")
 */
class OrderAddressController extends ApiController
{
    /**
     * @var OrderAddressRepository
     */
    private $orderAddressRepository;

    public function __construct(OrderAddressRepository $orderAddressRepository)
    {
        $this->orderAddressRepository = $orderAddressRepository;
    }

    /**
     * @Route("/orderAddress.{responseFormat}", name="api.orderAddress.list", methods={"GET"})
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

        $orderAddresses = $this->orderAddressRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $orderAddresses, 'total' => $orderAddresses->getTotal()],
            $context
        );
    }

    /**
     * @Route("/orderAddress/{orderAddressUuid}.{responseFormat}", name="api.orderAddress.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('orderAddressUuid');
        $orderAddresses = $this->orderAddressRepository->readBasic(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(['data' => $orderAddresses->get($uuid)], $context);
    }

    /**
     * @Route("/orderAddress.{responseFormat}", name="api.orderAddress.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->orderAddressRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $orderAddresses = $this->orderAddressRepository->readBasic(
            $createEvent->getOrderAddressUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $orderAddresses,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/orderAddress.{responseFormat}", name="api.orderAddress.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->orderAddressRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $orderAddresses = $this->orderAddressRepository->readBasic(
            $createEvent->getOrderAddressUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $orderAddresses,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/orderAddress.{responseFormat}", name="api.orderAddress.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->orderAddressRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $orderAddresses = $this->orderAddressRepository->readBasic(
            $createEvent->getOrderAddressUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $orderAddresses,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/orderAddress/{orderAddressUuid}.{responseFormat}", name="api.orderAddress.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('orderAddressUuid');

        $updateEvent = $this->orderAddressRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $orderAddresses = $this->orderAddressRepository->readBasic(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $orderAddresses->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/orderAddress.{responseFormat}", name="api.orderAddress.delete", methods={"DELETE"})
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
        return 'orderAddresses';
    }

    protected function getXmlChildKey(): string
    {
        return 'orderAddress';
    }
}
