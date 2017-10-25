<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\PriceGroup\Repository\PriceGroupRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.price_group.api_controller", path="/api")
 */
class PriceGroupController extends ApiController
{
    /**
     * @var PriceGroupRepository
     */
    private $priceGroupRepository;

    public function __construct(PriceGroupRepository $priceGroupRepository)
    {
        $this->priceGroupRepository = $priceGroupRepository;
    }

    /**
     * @Route("/priceGroup.{responseFormat}", name="api.priceGroup.list", methods={"GET"})
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

        $priceGroups = $this->priceGroupRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $priceGroups, 'total' => $priceGroups->getTotal()],
            $context
        );
    }

    /**
     * @Route("/priceGroup/{priceGroupUuid}.{responseFormat}", name="api.priceGroup.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('priceGroupUuid');
        $priceGroups = $this->priceGroupRepository->readDetail(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(['data' => $priceGroups->get($uuid)], $context);
    }

    /**
     * @Route("/priceGroup.{responseFormat}", name="api.priceGroup.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->priceGroupRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $priceGroups = $this->priceGroupRepository->readBasic(
            $createEvent->getPriceGroupUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $priceGroups,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/priceGroup.{responseFormat}", name="api.priceGroup.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->priceGroupRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $priceGroups = $this->priceGroupRepository->readBasic(
            $createEvent->getPriceGroupUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $priceGroups,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/priceGroup.{responseFormat}", name="api.priceGroup.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->priceGroupRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $priceGroups = $this->priceGroupRepository->readBasic(
            $createEvent->getPriceGroupUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $priceGroups,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/priceGroup/{priceGroupUuid}.{responseFormat}", name="api.priceGroup.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('priceGroupUuid');

        $updateEvent = $this->priceGroupRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $priceGroups = $this->priceGroupRepository->readDetail(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $priceGroups->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/priceGroup.{responseFormat}", name="api.priceGroup.delete", methods={"DELETE"})
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
        return 'priceGroups';
    }

    protected function getXmlChildKey(): string
    {
        return 'priceGroup';
    }
}
