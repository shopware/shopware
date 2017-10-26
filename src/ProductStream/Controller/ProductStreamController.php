<?php declare(strict_types=1);

namespace Shopware\ProductStream\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\ProductStream\Repository\ProductStreamRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.product_stream.api_controller", path="/api")
 */
class ProductStreamController extends ApiController
{
    /**
     * @var ProductStreamRepository
     */
    private $productStreamRepository;

    public function __construct(ProductStreamRepository $productStreamRepository)
    {
        $this->productStreamRepository = $productStreamRepository;
    }

    /**
     * @Route("/productStream.{responseFormat}", name="api.productStream.list", methods={"GET"})
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

        $productStreams = $this->productStreamRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $productStreams, 'total' => $productStreams->getTotal()],
            $context
        );
    }

    /**
     * @Route("/productStream/{productStreamUuid}.{responseFormat}", name="api.productStream.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('productStreamUuid');
        $productStreams = $this->productStreamRepository->readBasic(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(['data' => $productStreams->get($uuid)], $context);
    }

    /**
     * @Route("/productStream.{responseFormat}", name="api.productStream.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->productStreamRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productStreams = $this->productStreamRepository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productStreams,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productStream.{responseFormat}", name="api.productStream.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->productStreamRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productStreams = $this->productStreamRepository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productStreams,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productStream.{responseFormat}", name="api.productStream.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->productStreamRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productStreams = $this->productStreamRepository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productStreams,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productStream/{productStreamUuid}.{responseFormat}", name="api.productStream.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('productStreamUuid');

        $updateEvent = $this->productStreamRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $productStreams = $this->productStreamRepository->readBasic(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $productStreams->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/productStream.{responseFormat}", name="api.productStream.delete", methods={"DELETE"})
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
        return 'productStreams';
    }

    protected function getXmlChildKey(): string
    {
        return 'productStream';
    }
}
