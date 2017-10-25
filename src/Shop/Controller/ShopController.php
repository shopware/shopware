<?php declare(strict_types=1);

namespace Shopware\Shop\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Shopware\Shop\Repository\ShopRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.shop.api_controller", path="/api")
 */
class ShopController extends ApiController
{
    /**
     * @var ShopRepository
     */
    private $shopRepository;

    public function __construct(ShopRepository $shopRepository)
    {
        $this->shopRepository = $shopRepository;
    }

    /**
     * @Route("/shop.{responseFormat}", name="api.shop.list", methods={"GET"})
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

        $shops = $this->shopRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $shops, 'total' => $shops->getTotal()],
            $context
        );
    }

    /**
     * @Route("/shop/{shopUuid}.{responseFormat}", name="api.shop.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('shopUuid');
        $shops = $this->shopRepository->readDetail(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(['data' => $shops->get($uuid)], $context);
    }

    /**
     * @Route("/shop.{responseFormat}", name="api.shop.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->shopRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $shops = $this->shopRepository->readBasic(
            $createEvent->getShopUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $shops,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/shop.{responseFormat}", name="api.shop.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->shopRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $shops = $this->shopRepository->readBasic(
            $createEvent->getShopUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $shops,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/shop.{responseFormat}", name="api.shop.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->shopRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $shops = $this->shopRepository->readBasic(
            $createEvent->getShopUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $shops,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/shop/{shopUuid}.{responseFormat}", name="api.shop.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('shopUuid');

        $updateEvent = $this->shopRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $shops = $this->shopRepository->readDetail(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $shops->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/shop.{responseFormat}", name="api.shop.delete", methods={"DELETE"})
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
        return 'shops';
    }

    protected function getXmlChildKey(): string
    {
        return 'shop';
    }
}
