<?php declare(strict_types=1);

namespace Shopware\ProductVariant\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Parser\QueryStringParser;
use Shopware\Product\Repository\ProductRepository;
use Shopware\Rest\ApiContext;
use Shopware\Rest\RestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.variant.api_controller", path="/api")
 */
class ProductVariantController extends RestController
{
    /**
     * @var ProductRepository
     */
    private $repository;

    public function __construct(ProductRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @Route("/variant.{responseFormat}", name="api.variant.list", methods={"GET"})
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

        $variants = $this->repository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $variants, 'total' => $variants->getTotal()],
            $context
        );
    }

    /**
     * @Route("/variant/{productUuid}.{responseFormat}", name="api.variant.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('productUuid');
        $variants = $this->repository->readBasic(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(['data' => $variants->get($uuid)], $context);
    }

    /**
     * @Route("/variant.{responseFormat}", name="api.variant.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->repository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $variants = $this->repository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $variants,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/variant.{responseFormat}", name="api.variant.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->repository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $variants = $this->repository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $variants,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/variant.{responseFormat}", name="api.variant.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->repository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $variants = $this->repository->readBasic(
            $createEvent->getUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $variants,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/variant/{productUuid}.{responseFormat}", name="api.variant.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('productUuid');

        $updateEvent = $this->repository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $variants = $this->repository->readBasic(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $variants->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/variant.{responseFormat}", name="api.variant.delete", methods={"DELETE"})
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
        return 'variants';
    }

    protected function getXmlChildKey(): string
    {
        return 'variant';
    }
}
