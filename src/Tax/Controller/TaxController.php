<?php declare(strict_types=1);

namespace Shopware\Tax\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Shopware\Tax\Repository\TaxRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.tax.api_controller", path="/api")
 */
class TaxController extends ApiController
{
    /**
     * @var TaxRepository
     */
    private $taxRepository;

    public function __construct(TaxRepository $taxRepository)
    {
        $this->taxRepository = $taxRepository;
    }

    /**
     * @Route("/tax.{responseFormat}", name="api.tax.list", methods={"GET"})
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

        $taxes = $this->taxRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $taxes, 'total' => $taxes->getTotal()],
            $context
        );
    }

    /**
     * @Route("/tax/{taxUuid}.{responseFormat}", name="api.tax.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('taxUuid');
        $taxes = $this->taxRepository->readBasic(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(['data' => $taxes->get($uuid)], $context);
    }

    /**
     * @Route("/tax.{responseFormat}", name="api.tax.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->taxRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $taxes = $this->taxRepository->readBasic(
            $createEvent->getTaxUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $taxes,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/tax.{responseFormat}", name="api.tax.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->taxRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $taxes = $this->taxRepository->readBasic(
            $createEvent->getTaxUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $taxes,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/tax.{responseFormat}", name="api.tax.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->taxRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $taxes = $this->taxRepository->readBasic(
            $createEvent->getTaxUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $taxes,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/tax/{taxUuid}.{responseFormat}", name="api.tax.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('taxUuid');

        $updateEvent = $this->taxRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $taxes = $this->taxRepository->readBasic(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $taxes->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/tax.{responseFormat}", name="api.tax.delete", methods={"DELETE"})
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
        return 'taxes';
    }

    protected function getXmlChildKey(): string
    {
        return 'tax';
    }
}
