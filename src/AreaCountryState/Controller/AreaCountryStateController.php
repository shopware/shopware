<?php declare(strict_types=1);

namespace Shopware\AreaCountryState\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Api\ResultFormat;
use Shopware\AreaCountryState\Repository\AreaCountryStateRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.area_country_state.api_controller", path="/api")
 */
class AreaCountryStateController extends ApiController
{
    /**
     * @var AreaCountryStateRepository
     */
    private $areaCountryStateRepository;

    public function __construct(AreaCountryStateRepository $areaCountryStateRepository)
    {
        $this->areaCountryStateRepository = $areaCountryStateRepository;
    }

    public function getXmlRootKey(): string
    {
        return 'areaCountryStates';
    }

    public function getXmlChildKey(): string
    {
        return 'areaCountryState';
    }

    /**
     * @Route("/areaCountryState.{responseFormat}", name="api.areaCountryState.list", methods={"GET"})
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
            $parser = new QueryStringParser();
            $criteria->addFilter(
                $parser->fromUrl($request->query->get('query'))
            );
        }

        $criteria->setFetchCount(true);

        $searchResult = $this->areaCountryStateRepository->searchUuids(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        switch ($context->getResultFormat()) {
            case ResultFormat::BASIC:
                $areaCountryStates = $this->areaCountryStateRepository->read(
                    $searchResult->getUuids(),
                    $context->getShopContext()->getTranslationContext()
                );
                break;
            default:
                throw new \RuntimeException('Result format not supported.');
        }

        $response = [
            'data' => $areaCountryStates,
            'total' => $searchResult->getTotal(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/areaCountryState/{areaCountryStateUuid}.{responseFormat}", name="api.areaCountryState.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('areaCountryStateUuid');
        $areaCountryStates = $this->areaCountryStateRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse($areaCountryStates->get($uuid), $context);
    }

    /**
     * @Route("/areaCountryState.{responseFormat}", name="api.areaCountryState.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->areaCountryStateRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $areaCountryStates = $this->areaCountryStateRepository->read(
            $createEvent->getAreaCountryStateUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $areaCountryStates,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/areaCountryState.{responseFormat}", name="api.areaCountryState.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->areaCountryStateRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $areaCountryStates = $this->areaCountryStateRepository->read(
            $createEvent->getAreaCountryStateUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $areaCountryStates,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/areaCountryState.{responseFormat}", name="api.areaCountryState.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->areaCountryStateRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $areaCountryStates = $this->areaCountryStateRepository->read(
            $createEvent->getAreaCountryStateUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $areaCountryStates,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/areaCountryState/{areaCountryStateUuid}.{responseFormat}", name="api.areaCountryState.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('areaCountryStateUuid');

        $updateEvent = $this->areaCountryStateRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $areaCountryStates = $this->areaCountryStateRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $areaCountryStates->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/areaCountryState.{responseFormat}", name="api.areaCountryState.delete", methods={"DELETE"})
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
