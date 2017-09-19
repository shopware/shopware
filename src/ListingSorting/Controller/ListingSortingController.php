<?php declare(strict_types=1);

namespace Shopware\ListingSorting\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Api\ResultFormat;
use Shopware\ListingSorting\Repository\ListingSortingRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.listing_sorting.api_controller", path="/api")
 */
class ListingSortingController extends ApiController
{
    /**
     * @var ListingSortingRepository
     */
    private $listingSortingRepository;

    public function __construct(ListingSortingRepository $listingSortingRepository)
    {
        $this->listingSortingRepository = $listingSortingRepository;
    }

    public function getXmlRootKey(): string
    {
        return 'listingSortings';
    }

    public function getXmlChildKey(): string
    {
        return 'listingSorting';
    }

    /**
     * @Route("/listingSorting.{responseFormat}", name="api.listingSorting.list", methods={"GET"})
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

        $searchResult = $this->listingSortingRepository->searchUuids(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        switch ($context->getResultFormat()) {
            case ResultFormat::BASIC:
                $listingSortings = $this->listingSortingRepository->read(
                    $searchResult->getUuids(),
                    $context->getShopContext()->getTranslationContext()
                );
                break;
            default:
                throw new \RuntimeException('Result format not supported.');
        }

        $response = [
            'data' => $listingSortings,
            'total' => $searchResult->getTotal(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/listingSorting/{listingSortingUuid}.{responseFormat}", name="api.listingSorting.detail", methods={"GET"})
     * @param Request $request
     * @param ApiContext $context
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('listingSortingUuid');
        $listingSortings = $this->listingSortingRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse($listingSortings->get($uuid), $context);
    }

    /**
     * @Route("/listingSorting.{responseFormat}", name="api.listingSorting.create", methods={"POST"})
     * @param ApiContext $context
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->listingSortingRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $listingSortings = $this->listingSortingRepository->read(
            $createEvent->getListingSortingUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $listingSortings,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/listingSorting.{responseFormat}", name="api.listingSorting.upsert", methods={"PUT"})
     * @param ApiContext $context
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->listingSortingRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $listingSortings = $this->listingSortingRepository->read(
            $createEvent->getListingSortingUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $listingSortings,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/listingSorting.{responseFormat}", name="api.listingSorting.update", methods={"PATCH"})
     * @param ApiContext $context
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->listingSortingRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $listingSortings = $this->listingSortingRepository->read(
            $createEvent->getListingSortingUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $listingSortings,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/listingSorting/{listingSortingUuid}.{responseFormat}", name="api.listingSorting.single_update", methods={"PATCH"})
     * @param Request $request
     * @param ApiContext $context
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('listingSortingUuid');

        $updateEvent = $this->listingSortingRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $listingSortings = $this->listingSortingRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $listingSortings->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/listingSorting.{responseFormat}", name="api.listingSorting.delete", methods={"DELETE"})
     * @param ApiContext $context
     * @return Response
     */
    public function deleteAction(ApiContext $context): Response
    {
        $result = [];

        return $this->createResponse($result, $context);
    }
}
