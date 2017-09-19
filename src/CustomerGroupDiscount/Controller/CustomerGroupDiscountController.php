<?php declare(strict_types=1);

namespace Shopware\CustomerGroupDiscount\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Api\ResultFormat;
use Shopware\CustomerGroupDiscount\Repository\CustomerGroupDiscountRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.customer_group_discount.api_controller", path="/api")
 */
class CustomerGroupDiscountController extends ApiController
{
    /**
     * @var CustomerGroupDiscountRepository
     */
    private $customerGroupDiscountRepository;

    public function __construct(CustomerGroupDiscountRepository $customerGroupDiscountRepository)
    {
        $this->customerGroupDiscountRepository = $customerGroupDiscountRepository;
    }

    public function getXmlRootKey(): string
    {
        return 'customerGroupDiscounts';
    }

    public function getXmlChildKey(): string
    {
        return 'customerGroupDiscount';
    }

    /**
     * @Route("/customerGroupDiscount.{responseFormat}", name="api.customerGroupDiscount.list", methods={"GET"})
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

        $searchResult = $this->customerGroupDiscountRepository->searchUuids(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        switch ($context->getResultFormat()) {
            case ResultFormat::BASIC:
                $customerGroupDiscounts = $this->customerGroupDiscountRepository->read(
                    $searchResult->getUuids(),
                    $context->getShopContext()->getTranslationContext()
                );
                break;
            default:
                throw new \RuntimeException('Result format not supported.');
        }

        $response = [
            'data' => $customerGroupDiscounts,
            'total' => $searchResult->getTotal(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/customerGroupDiscount/{customerGroupDiscountUuid}.{responseFormat}", name="api.customerGroupDiscount.detail", methods={"GET"})
     * @param Request $request
     * @param ApiContext $context
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('customerGroupDiscountUuid');
        $customerGroupDiscounts = $this->customerGroupDiscountRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse($customerGroupDiscounts->get($uuid), $context);
    }

    /**
     * @Route("/customerGroupDiscount.{responseFormat}", name="api.customerGroupDiscount.create", methods={"POST"})
     * @param ApiContext $context
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->customerGroupDiscountRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $customerGroupDiscounts = $this->customerGroupDiscountRepository->read(
            $createEvent->getCustomerGroupDiscountUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $customerGroupDiscounts,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/customerGroupDiscount.{responseFormat}", name="api.customerGroupDiscount.upsert", methods={"PUT"})
     * @param ApiContext $context
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->customerGroupDiscountRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $customerGroupDiscounts = $this->customerGroupDiscountRepository->read(
            $createEvent->getCustomerGroupDiscountUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $customerGroupDiscounts,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/customerGroupDiscount.{responseFormat}", name="api.customerGroupDiscount.update", methods={"PATCH"})
     * @param ApiContext $context
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->customerGroupDiscountRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $customerGroupDiscounts = $this->customerGroupDiscountRepository->read(
            $createEvent->getCustomerGroupDiscountUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $customerGroupDiscounts,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/customerGroupDiscount/{customerGroupDiscountUuid}.{responseFormat}", name="api.customerGroupDiscount.single_update", methods={"PATCH"})
     * @param Request $request
     * @param ApiContext $context
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('customerGroupDiscountUuid');

        $updateEvent = $this->customerGroupDiscountRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $customerGroupDiscounts = $this->customerGroupDiscountRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $customerGroupDiscounts->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/customerGroupDiscount.{responseFormat}", name="api.customerGroupDiscount.delete", methods={"DELETE"})
     * @param ApiContext $context
     * @return Response
     */
    public function deleteAction(ApiContext $context): Response
    {
        $result = [];

        return $this->createResponse($result, $context);
    }
}
