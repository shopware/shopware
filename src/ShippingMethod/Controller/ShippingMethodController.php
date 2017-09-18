<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Api\ResultFormat;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Shopware\ShippingMethod\Repository\ShippingMethodRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.shipping_method.api_controller", path="/api")
 */
class ShippingMethodController extends ApiController
{
    /**
     * @var ShippingMethodRepository
     */
    private $shippingMethodRepository;

    public function __construct(ShippingMethodRepository $shippingMethodRepository)
    {
        $this->shippingMethodRepository = $shippingMethodRepository;
    }

    public function getXmlRootKey(): string
    {
        return 'shippingMethods';
    }

    public function getXmlChildKey(): string
    {
        return 'shippingMethod';
    }

    /**
     * @Route("/shippingMethod.{responseFormat}", name="api.shippingMethod.list", methods={"GET"})
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

        $searchResult = $this->shippingMethodRepository->searchUuids(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        switch ($context->getResultFormat()) {
            case ResultFormat::BASIC:
                $shippingMethods = $this->shippingMethodRepository->read(
                    $searchResult->getUuids(),
                    $context->getShopContext()->getTranslationContext()
                );
                break;
            default:
                throw new \RuntimeException('Result format not supported.');
        }

        $response = [
            'data' => $shippingMethods,
            'total' => $searchResult->getTotal(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/shippingMethod/{shippingMethodUuid}.{responseFormat}", name="api.shippingMethod.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('shippingMethodUuid');
        $shippingMethods = $this->shippingMethodRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse($shippingMethods->get($uuid), $context);
    }

    /**
     * @Route("/shippingMethod.{responseFormat}", name="api.shippingMethod.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->shippingMethodRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $shippingMethods = $this->shippingMethodRepository->read(
            $createEvent->getShippingMethodUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $shippingMethods,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/shippingMethod.{responseFormat}", name="api.shippingMethod.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->shippingMethodRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $shippingMethods = $this->shippingMethodRepository->read(
            $createEvent->getShippingMethodUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $shippingMethods,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/shippingMethod.{responseFormat}", name="api.shippingMethod.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->shippingMethodRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $shippingMethods = $this->shippingMethodRepository->read(
            $createEvent->getShippingMethodUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $shippingMethods,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/shippingMethod/{shippingMethodUuid}.{responseFormat}", name="api.shippingMethod.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('shippingMethodUuid');

        $updateEvent = $this->shippingMethodRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $shippingMethods = $this->shippingMethodRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $shippingMethods->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/shippingMethod.{responseFormat}", name="api.shippingMethod.delete", methods={"DELETE"})
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
