<?php declare(strict_types=1);

namespace Shopware\CustomerAddress\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Api\ResultFormat;
use Shopware\CustomerAddress\Repository\CustomerAddressRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.customer_address.api_controller", path="/api")
 */
class CustomerAddressController extends ApiController
{
    /**
     * @var CustomerAddressRepository
     */
    private $customerAddressRepository;

    public function __construct(CustomerAddressRepository $customerAddressRepository)
    {
        $this->customerAddressRepository = $customerAddressRepository;
    }

    public function getXmlRootKey(): string
    {
        return 'customerAddresss';
    }

    public function getXmlChildKey(): string
    {
        return 'customerAddress';
    }

    /**
     * @Route("/customerAddress.{responseFormat}", name="api.customerAddress.list", methods={"GET"})
     * @param Request $request
     * @param ApiContext $context
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

        $searchResult = $this->customerAddressRepository->searchUuids(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        switch ($context->getResultFormat()) {
            case ResultFormat::BASIC:
                $customerAddresss = $this->customerAddressRepository->read(
                    $searchResult->getUuids(),
                    $context->getShopContext()->getTranslationContext()
                );
                break;
            default:
                throw new \RuntimeException('Result format not supported.');
        }

        $response = [
            'data' => $customerAddresss,
            'total' => $searchResult->getTotal(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/customerAddress/{customerAddressUuid}.{responseFormat}", name="api.customerAddress.detail", methods={"GET"})
     * @param Request $request
     * @param ApiContext $context
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('customerAddressUuid');
        $customerAddresss = $this->customerAddressRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse($customerAddresss->get($uuid), $context);
    }

    /**
     * @Route("/customerAddress.{responseFormat}", name="api.customerAddress.create", methods={"POST"})
     * @param ApiContext $context
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->customerAddressRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $customerAddresss = $this->customerAddressRepository->read(
            $createEvent->getCustomerAddressUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $customerAddresss,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/customerAddress.{responseFormat}", name="api.customerAddress.upsert", methods={"PUT"})
     * @param ApiContext $context
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->customerAddressRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $customerAddresss = $this->customerAddressRepository->read(
            $createEvent->getCustomerAddressUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $customerAddresss,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/customerAddress.{responseFormat}", name="api.customerAddress.update", methods={"PATCH"})
     * @param ApiContext $context
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->customerAddressRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $customerAddresss = $this->customerAddressRepository->read(
            $createEvent->getCustomerAddressUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $customerAddresss,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/customerAddress/{customerAddressUuid}.{responseFormat}", name="api.customerAddress.single_update", methods={"PATCH"})
     * @param Request $request
     * @param ApiContext $context
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('customerAddressUuid');

        $updateEvent = $this->customerAddressRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $customerAddresss = $this->customerAddressRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $customerAddresss->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/customerAddress.{responseFormat}", name="api.customerAddress.delete", methods={"DELETE"})
     * @param ApiContext $context
     * @return Response
     */
    public function deleteAction(ApiContext $context): Response
    {
        $result = [];

        return $this->createResponse($result, $context);
    }
}
