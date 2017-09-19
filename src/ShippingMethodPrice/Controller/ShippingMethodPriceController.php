<?php declare(strict_types=1);

namespace Shopware\ShippingMethodPrice\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Shopware\ShippingMethodPrice\Repository\ShippingMethodPriceRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.shipping_method_price.api_controller", path="/api")
 */
class ShippingMethodPriceController extends ApiController
{
    /**
     * @var ShippingMethodPriceRepository
     */
    private $shippingMethodPriceRepository;

    public function __construct(ShippingMethodPriceRepository $shippingMethodPriceRepository)
    {
        $this->shippingMethodPriceRepository = $shippingMethodPriceRepository;
    }

    public function getXmlRootKey(): string
    {
        return 'shippingMethodPrices';
    }

    public function getXmlChildKey(): string
    {
        return 'shippingMethodPrice';
    }

    /**
     * @Route("/shippingMethodPrice.{responseFormat}", name="api.shippingMethodPrice.list", methods={"GET"})
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

        $shippingMethodPrices = $this->shippingMethodPriceRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $shippingMethodPrices,
            'total' => $shippingMethodPrices->getTotal(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/shippingMethodPrice/{shippingMethodPriceUuid}.{responseFormat}", name="api.shippingMethodPrice.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('shippingMethodPriceUuid');
        $shippingMethodPrices = $this->shippingMethodPriceRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse($shippingMethodPrices->get($uuid), $context);
    }

    /**
     * @Route("/shippingMethodPrice.{responseFormat}", name="api.shippingMethodPrice.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->shippingMethodPriceRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $shippingMethodPrices = $this->shippingMethodPriceRepository->read(
            $createEvent->getShippingMethodPriceUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $shippingMethodPrices,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/shippingMethodPrice.{responseFormat}", name="api.shippingMethodPrice.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->shippingMethodPriceRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $shippingMethodPrices = $this->shippingMethodPriceRepository->read(
            $createEvent->getShippingMethodPriceUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $shippingMethodPrices,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/shippingMethodPrice.{responseFormat}", name="api.shippingMethodPrice.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->shippingMethodPriceRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $shippingMethodPrices = $this->shippingMethodPriceRepository->read(
            $createEvent->getShippingMethodPriceUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $shippingMethodPrices,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/shippingMethodPrice/{shippingMethodPriceUuid}.{responseFormat}", name="api.shippingMethodPrice.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('shippingMethodPriceUuid');

        $updateEvent = $this->shippingMethodPriceRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $shippingMethodPrices = $this->shippingMethodPriceRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $shippingMethodPrices->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/shippingMethodPrice.{responseFormat}", name="api.shippingMethodPrice.delete", methods={"DELETE"})
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
