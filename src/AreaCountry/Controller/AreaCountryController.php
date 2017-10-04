<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\AreaCountry\Repository\AreaCountryRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.area_country.api_controller", path="/api")
 */
class AreaCountryController extends ApiController
{
    /**
     * @var AreaCountryRepository
     */
    private $areaCountryRepository;

    public function __construct(AreaCountryRepository $areaCountryRepository)
    {
        $this->areaCountryRepository = $areaCountryRepository;
    }

    /**
     * @Route("/areaCountry.{responseFormat}", name="api.areaCountry.list", methods={"GET"})
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

        $areaCountries = $this->areaCountryRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $areaCountries, 'total' => $areaCountries->getTotal()],
            $context
        );
    }

    /**
     * @Route("/areaCountry/{areaCountryUuid}.{responseFormat}", name="api.areaCountry.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('areaCountryUuid');
        $areaCountries = $this->areaCountryRepository->readDetail(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(['data' => $areaCountries->get($uuid)], $context);
    }

    /**
     * @Route("/areaCountry.{responseFormat}", name="api.areaCountry.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->areaCountryRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $areaCountries = $this->areaCountryRepository->read(
            $createEvent->getAreaCountryUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $areaCountries,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/areaCountry.{responseFormat}", name="api.areaCountry.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->areaCountryRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $areaCountries = $this->areaCountryRepository->read(
            $createEvent->getAreaCountryUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $areaCountries,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/areaCountry.{responseFormat}", name="api.areaCountry.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->areaCountryRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $areaCountries = $this->areaCountryRepository->read(
            $createEvent->getAreaCountryUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $areaCountries,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/areaCountry/{areaCountryUuid}.{responseFormat}", name="api.areaCountry.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('areaCountryUuid');

        $updateEvent = $this->areaCountryRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $areaCountries = $this->areaCountryRepository->readDetail(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $areaCountries->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/areaCountry.{responseFormat}", name="api.areaCountry.delete", methods={"DELETE"})
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
        return 'areaCountries';
    }

    protected function getXmlChildKey(): string
    {
        return 'areaCountry';
    }
}
