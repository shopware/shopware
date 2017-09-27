<?php declare(strict_types=1);

namespace Shopware\ProductMedia\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\ProductMedia\Repository\ProductMediaRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.product_media.api_controller", path="/api")
 */
class ProductMediaController extends ApiController
{
    /**
     * @var ProductMediaRepository
     */
    private $productMediaRepository;

    public function __construct(ProductMediaRepository $productMediaRepository)
    {
        $this->productMediaRepository = $productMediaRepository;
    }

    public function getXmlRootKey(): string
    {
        return 'productMedias';
    }

    public function getXmlChildKey(): string
    {
        return 'productMedia';
    }

    /**
     * @Route("/productMedia.{responseFormat}", name="api.productMedia.list", methods={"GET"})
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

        $productMedias = $this->productMediaRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $productMedias, 'total' => $productMedias->getTotal()],
            $context
        );
    }

    /**
     * @Route("/productMedia/{productMediaUuid}.{responseFormat}", name="api.productMedia.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('productMediaUuid');
        $productMedias = $this->productMediaRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(['data' => $productMedias->get($uuid)], $context);
    }

    /**
     * @Route("/productMedia.{responseFormat}", name="api.productMedia.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->productMediaRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productMedias = $this->productMediaRepository->read(
            $createEvent->getProductMediaUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productMedias,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productMedia.{responseFormat}", name="api.productMedia.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->productMediaRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productMedias = $this->productMediaRepository->read(
            $createEvent->getProductMediaUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productMedias,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productMedia.{responseFormat}", name="api.productMedia.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->productMediaRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productMedias = $this->productMediaRepository->read(
            $createEvent->getProductMediaUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productMedias,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productMedia/{productMediaUuid}.{responseFormat}", name="api.productMedia.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('productMediaUuid');

        $updateEvent = $this->productMediaRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $productMedias = $this->productMediaRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $productMedias->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/productMedia.{responseFormat}", name="api.productMedia.delete", methods={"DELETE"})
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
}
