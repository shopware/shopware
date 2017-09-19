<?php declare(strict_types=1);

namespace Shopware\Unit\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Shopware\Unit\Repository\UnitRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.unit.api_controller", path="/api")
 */
class UnitController extends ApiController
{
    /**
     * @var UnitRepository
     */
    private $unitRepository;

    public function __construct(UnitRepository $unitRepository)
    {
        $this->unitRepository = $unitRepository;
    }

    public function getXmlRootKey(): string
    {
        return 'units';
    }

    public function getXmlChildKey(): string
    {
        return 'unit';
    }

    /**
     * @Route("/unit.{responseFormat}", name="api.unit.list", methods={"GET"})
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

        $units = $this->unitRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $units->getElements(),
            'total' => $units->getTotal(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/unit/{unitUuid}.{responseFormat}", name="api.unit.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('unitUuid');
        $units = $this->unitRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse($units->get($uuid), $context);
    }

    /**
     * @Route("/unit.{responseFormat}", name="api.unit.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->unitRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $units = $this->unitRepository->read(
            $createEvent->getUnitUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $units,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/unit.{responseFormat}", name="api.unit.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->unitRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $units = $this->unitRepository->read(
            $createEvent->getUnitUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $units,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/unit.{responseFormat}", name="api.unit.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->unitRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $units = $this->unitRepository->read(
            $createEvent->getUnitUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $units,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/unit/{unitUuid}.{responseFormat}", name="api.unit.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('unitUuid');

        $updateEvent = $this->unitRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $units = $this->unitRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $units->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/unit.{responseFormat}", name="api.unit.delete", methods={"DELETE"})
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
