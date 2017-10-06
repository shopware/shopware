<?php declare(strict_types=1);

namespace Shopware\ProductVoteAverage\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\ProductVoteAverage\Repository\ProductVoteAverageRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.product_vote_average_ro.api_controller", path="/api")
 */
class ProductVoteAverageController extends ApiController
{
    /**
     * @var ProductVoteAverageRepository
     */
    private $productVoteAverageRepository;

    public function __construct(ProductVoteAverageRepository $productVoteAverageRepository)
    {
        $this->productVoteAverageRepository = $productVoteAverageRepository;
    }

    /**
     * @Route("/productVoteAverage.{responseFormat}", name="api.productVoteAverage.list", methods={"GET"})
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

        $productVoteAverages = $this->productVoteAverageRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $productVoteAverages, 'total' => $productVoteAverages->getTotal()],
            $context
        );
    }

    /**
     * @Route("/productVoteAverage/{productVoteAverageUuid}.{responseFormat}", name="api.productVoteAverage.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('productVoteAverageUuid');
        $productVoteAverages = $this->productVoteAverageRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(['data' => $productVoteAverages->get($uuid)], $context);
    }

    /**
     * @Route("/productVoteAverage.{responseFormat}", name="api.productVoteAverage.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->productVoteAverageRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productVoteAverages = $this->productVoteAverageRepository->read(
            $createEvent->getProductVoteAverageUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productVoteAverages,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productVoteAverage.{responseFormat}", name="api.productVoteAverage.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->productVoteAverageRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productVoteAverages = $this->productVoteAverageRepository->read(
            $createEvent->getProductVoteAverageUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productVoteAverages,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productVoteAverage.{responseFormat}", name="api.productVoteAverage.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->productVoteAverageRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productVoteAverages = $this->productVoteAverageRepository->read(
            $createEvent->getProductVoteAverageUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productVoteAverages,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productVoteAverage/{productVoteAverageUuid}.{responseFormat}", name="api.productVoteAverage.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('productVoteAverageUuid');

        $updateEvent = $this->productVoteAverageRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $productVoteAverages = $this->productVoteAverageRepository->readDetail(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $productVoteAverages->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/productVoteAverage.{responseFormat}", name="api.productVoteAverage.delete", methods={"DELETE"})
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
        return 'productVoteAverages';
    }

    protected function getXmlChildKey(): string
    {
        return 'productVoteAverage';
    }
}
