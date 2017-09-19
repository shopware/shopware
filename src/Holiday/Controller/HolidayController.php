<?php declare(strict_types=1);

namespace Shopware\Holiday\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Holiday\Repository\HolidayRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.holiday.api_controller", path="/api")
 */
class HolidayController extends ApiController
{
    /**
     * @var HolidayRepository
     */
    private $holidayRepository;

    public function __construct(HolidayRepository $holidayRepository)
    {
        $this->holidayRepository = $holidayRepository;
    }

    public function getXmlRootKey(): string
    {
        return 'holidaies';
    }

    public function getXmlChildKey(): string
    {
        return 'holiday';
    }

    /**
     * @Route("/holiday.{responseFormat}", name="api.holiday.list", methods={"GET"})
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

        $holidaies = $this->holidayRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $holidaies->getElements(),
            'total' => $holidaies->getTotal(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/holiday/{holidayUuid}.{responseFormat}", name="api.holiday.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('holidayUuid');
        $holidaies = $this->holidayRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse($holidaies->get($uuid), $context);
    }

    /**
     * @Route("/holiday.{responseFormat}", name="api.holiday.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->holidayRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $holidaies = $this->holidayRepository->read(
            $createEvent->getHolidayUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $holidaies,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/holiday.{responseFormat}", name="api.holiday.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->holidayRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $holidaies = $this->holidayRepository->read(
            $createEvent->getHolidayUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $holidaies,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/holiday.{responseFormat}", name="api.holiday.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->holidayRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $holidaies = $this->holidayRepository->read(
            $createEvent->getHolidayUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $holidaies,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/holiday/{holidayUuid}.{responseFormat}", name="api.holiday.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('holidayUuid');

        $updateEvent = $this->holidayRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $holidaies = $this->holidayRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $holidaies->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/holiday.{responseFormat}", name="api.holiday.delete", methods={"DELETE"})
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
