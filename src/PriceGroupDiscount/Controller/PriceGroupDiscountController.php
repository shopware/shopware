<?php declare(strict_types=1);

namespace Shopware\PriceGroupDiscount\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\PriceGroupDiscount\Repository\PriceGroupDiscountRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.price_group_discount.api_controller", path="/api")
 */
class PriceGroupDiscountController extends ApiController
{
    /**
     * @var PriceGroupDiscountRepository
     */
    private $priceGroupDiscountRepository;

    public function __construct(PriceGroupDiscountRepository $priceGroupDiscountRepository)
    {
        $this->priceGroupDiscountRepository = $priceGroupDiscountRepository;
    }

    /**
     * @Route("/priceGroupDiscount.{responseFormat}", name="api.priceGroupDiscount.list", methods={"GET"})
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

        $priceGroupDiscounts = $this->priceGroupDiscountRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $priceGroupDiscounts, 'total' => $priceGroupDiscounts->getTotal()],
            $context
        );
    }

    /**
     * @Route("/priceGroupDiscount/{priceGroupDiscountUuid}.{responseFormat}", name="api.priceGroupDiscount.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('priceGroupDiscountUuid');
        $priceGroupDiscounts = $this->priceGroupDiscountRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(['data' => $priceGroupDiscounts->get($uuid)], $context);
    }

    /**
     * @Route("/priceGroupDiscount.{responseFormat}", name="api.priceGroupDiscount.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->priceGroupDiscountRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $priceGroupDiscounts = $this->priceGroupDiscountRepository->read(
            $createEvent->getPriceGroupDiscountUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $priceGroupDiscounts,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/priceGroupDiscount.{responseFormat}", name="api.priceGroupDiscount.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->priceGroupDiscountRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $priceGroupDiscounts = $this->priceGroupDiscountRepository->read(
            $createEvent->getPriceGroupDiscountUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $priceGroupDiscounts,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/priceGroupDiscount.{responseFormat}", name="api.priceGroupDiscount.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->priceGroupDiscountRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $priceGroupDiscounts = $this->priceGroupDiscountRepository->read(
            $createEvent->getPriceGroupDiscountUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $priceGroupDiscounts,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/priceGroupDiscount/{priceGroupDiscountUuid}.{responseFormat}", name="api.priceGroupDiscount.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('priceGroupDiscountUuid');

        $updateEvent = $this->priceGroupDiscountRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $priceGroupDiscounts = $this->priceGroupDiscountRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $priceGroupDiscounts->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/priceGroupDiscount.{responseFormat}", name="api.priceGroupDiscount.delete", methods={"DELETE"})
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
        return 'priceGroupDiscounts';
    }

    protected function getXmlChildKey(): string
    {
        return 'priceGroupDiscount';
    }
}
