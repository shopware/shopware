<?php declare(strict_types=1);

namespace Shopware\Tax\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Api\ResultFormat;
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

    public function getXmlRootKey(): string
    {
        return 'taxs';
    }

    public function getXmlChildKey(): string
    {
        return 'tax';
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
            $parser = new QueryStringParser();
            $criteria->addFilter(
                $parser->fromUrl($request->query->get('query'))
            );
        }

        $criteria->setFetchCount(true);

        $searchResult = $this->taxRepository->searchUuids(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        switch ($context->getResultFormat()) {
            case ResultFormat::BASIC:
                $taxs = $this->taxRepository->read(
                    $searchResult->getUuids(),
                    $context->getShopContext()->getTranslationContext()
                );
                break;
            default:
                throw new \RuntimeException('Result format not supported.');
        }

        $response = [
            'data' => $taxs,
            'total' => $searchResult->getTotal(),
        ];

        return $this->createResponse($response, $context);
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
        $taxs = $this->taxRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse($taxs->get($uuid), $context);
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

        $taxs = $this->taxRepository->read(
            $createEvent->getTaxUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $taxs,
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

        $taxs = $this->taxRepository->read(
            $createEvent->getTaxUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $taxs,
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

        $taxs = $this->taxRepository->read(
            $createEvent->getTaxUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $taxs,
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

        $taxs = $this->taxRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $taxs->get($payload['uuid'])],
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
        $result = [];

        return $this->createResponse($result, $context);
    }
}
