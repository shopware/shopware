<?php declare(strict_types=1);

namespace Shopware\Category\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Category\Repository\CategoryRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.category.api_controller", path="/api")
 */
class CategoryController extends ApiController
{
    /**
     * @var CategoryRepository
     */
    private $categoryRepository;

    public function __construct(CategoryRepository $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    public function getXmlRootKey(): string
    {
        return 'categories';
    }

    public function getXmlChildKey(): string
    {
        return 'category';
    }

    /**
     * @Route("/category.{responseFormat}", name="api.category.list", methods={"GET"})
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

        $categories = $this->categoryRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $categories,
            'total' => $categories->getTotal(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/category/{categoryUuid}.{responseFormat}", name="api.category.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('categoryUuid');
        $categories = $this->categoryRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse($categories->get($uuid), $context);
    }

    /**
     * @Route("/category.{responseFormat}", name="api.category.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->categoryRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $categories = $this->categoryRepository->read(
            $createEvent->getCategoryUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $categories,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/category.{responseFormat}", name="api.category.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->categoryRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $categories = $this->categoryRepository->read(
            $createEvent->getCategoryUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $categories,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/category.{responseFormat}", name="api.category.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->categoryRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $categories = $this->categoryRepository->read(
            $createEvent->getCategoryUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $categories,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/category/{categoryUuid}.{responseFormat}", name="api.category.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('categoryUuid');

        $updateEvent = $this->categoryRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $categories = $this->categoryRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $categories->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/category.{responseFormat}", name="api.category.delete", methods={"DELETE"})
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
