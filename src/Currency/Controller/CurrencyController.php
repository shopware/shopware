<?php declare(strict_types=1);

namespace Shopware\Currency\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Currency\Repository\CurrencyRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.currency.api_controller", path="/api")
 */
class CurrencyController extends ApiController
{
    /**
     * @var CurrencyRepository
     */
    private $currencyRepository;

    public function __construct(CurrencyRepository $currencyRepository)
    {
        $this->currencyRepository = $currencyRepository;
    }

    public function getXmlRootKey(): string
    {
        return 'currencies';
    }

    public function getXmlChildKey(): string
    {
        return 'currency';
    }

    /**
     * @Route("/currency.{responseFormat}", name="api.currency.list", methods={"GET"})
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

        $currencies = $this->currencyRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $currencies->getElements(),
            'total' => $currencies->getTotal(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/currency/{currencyUuid}.{responseFormat}", name="api.currency.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('currencyUuid');
        $currencies = $this->currencyRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse($currencies->get($uuid), $context);
    }

    /**
     * @Route("/currency.{responseFormat}", name="api.currency.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->currencyRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $currencies = $this->currencyRepository->read(
            $createEvent->getCurrencyUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $currencies,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/currency.{responseFormat}", name="api.currency.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->currencyRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $currencies = $this->currencyRepository->read(
            $createEvent->getCurrencyUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $currencies,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/currency.{responseFormat}", name="api.currency.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->currencyRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $currencies = $this->currencyRepository->read(
            $createEvent->getCurrencyUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $currencies,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/currency/{currencyUuid}.{responseFormat}", name="api.currency.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('currencyUuid');

        $updateEvent = $this->currencyRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $currencies = $this->currencyRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $currencies->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/currency.{responseFormat}", name="api.currency.delete", methods={"DELETE"})
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
