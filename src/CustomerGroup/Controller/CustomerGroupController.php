<?php declare(strict_types=1);

namespace Shopware\CustomerGroup\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Api\ResultFormat;
use Shopware\CustomerGroup\Repository\CustomerGroupRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.customer_group.api_controller", path="/api")
 */
class CustomerGroupController extends ApiController
{
    /**
     * @var CustomerGroupRepository
     */
    private $customerGroupRepository;

    public function __construct(CustomerGroupRepository $customerGroupRepository)
    {
        $this->customerGroupRepository = $customerGroupRepository;
    }

    public function getXmlRootKey(): string
    {
        return 'customerGroups';
    }

    public function getXmlChildKey(): string
    {
        return 'customerGroup';
    }

    /**
     * @Route("/customerGroup.{responseFormat}", name="api.customerGroup.list", methods={"GET"})
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

        $searchResult = $this->customerGroupRepository->searchUuids(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        switch ($context->getResultFormat()) {
            case ResultFormat::BASIC:
                $customerGroups = $this->customerGroupRepository->read(
                    $searchResult->getUuids(),
                    $context->getShopContext()->getTranslationContext()
                );
                break;
            default:
                throw new \RuntimeException('Result format not supported.');
        }

        $response = [
            'data' => $customerGroups,
            'total' => $searchResult->getTotal(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/customerGroup/{customerGroupUuid}.{responseFormat}", name="api.customerGroup.detail", methods={"GET"})
     * @param Request $request
     * @param ApiContext $context
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('customerGroupUuid');
        $customerGroups = $this->customerGroupRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse($customerGroups->get($uuid), $context);
    }

    /**
     * @Route("/customerGroup.{responseFormat}", name="api.customerGroup.create", methods={"POST"})
     * @param ApiContext $context
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->customerGroupRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $customerGroups = $this->customerGroupRepository->read(
            $createEvent->getCustomerGroupUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $customerGroups,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/customerGroup.{responseFormat}", name="api.customerGroup.upsert", methods={"PUT"})
     * @param ApiContext $context
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->customerGroupRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $customerGroups = $this->customerGroupRepository->read(
            $createEvent->getCustomerGroupUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $customerGroups,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/customerGroup.{responseFormat}", name="api.customerGroup.update", methods={"PATCH"})
     * @param ApiContext $context
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->customerGroupRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $customerGroups = $this->customerGroupRepository->read(
            $createEvent->getCustomerGroupUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $customerGroups,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/customerGroup/{customerGroupUuid}.{responseFormat}", name="api.customerGroup.single_update", methods={"PATCH"})
     * @param Request $request
     * @param ApiContext $context
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('customerGroupUuid');

        $updateEvent = $this->customerGroupRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $customerGroups = $this->customerGroupRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $customerGroups->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/customerGroup.{responseFormat}", name="api.customerGroup.delete", methods={"DELETE"})
     * @param ApiContext $context
     * @return Response
     */
    public function deleteAction(ApiContext $context): Response
    {
        $result = [];

        return $this->createResponse($result, $context);
    }
}
