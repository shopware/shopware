<?php declare(strict_types=1);

namespace Shopware\Category\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Api\ResultFormat;
use Shopware\Category\Event\CategoryWrittenEvent;
use Shopware\Category\Repository\CategoryRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.category.controller.category_controller", path="/api")
 */
class CategoryController extends ApiController
{
    public function getXmlRootKey(): string
    {
        return 'categories';
    }

    public function getXmlChildKey(): string
    {
        return 'category';
    }

    /**
     * @var CategoryRepository
     */
    private $categoryRepository;

    public function __construct(CategoryRepository $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * @Route("/category.{responseFormat}", name="api.category.list", methods={"GET"})
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

        $searchResult = $this->categoryRepository->searchUuids($criteria, $context->getShopContext()->getTranslationContext());

        switch ($context->getResultFormat()) {
            case ResultFormat::BASIC:
                $categories = $this->categoryRepository->read($searchResult->getUuids(), $context->getShopContext()->getTranslationContext());
                break;
            default:
                throw new \Exception('Result format not supported.');
        }

        $response = [
            'data' => $categories,
            'total' => $searchResult->getTotal()
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/category/{categoryUuid}.{responseFormat}", name="api.category.detail", methods={"GET"})
     */
    public function detailAction(Request $request, ApiContext $context)
    {
        $uuid = $request->get('categoryUuid');

        $categories = $this->categoryRepository->read([$uuid], $context->getShopContext()->getTranslationContext());
        $category = $categories->get($uuid);

        return $this->createResponse($category, $context);
    }

    /**
     * @Route("/category.{responseFormat}", name="api.category.create", methods={"POST"})
     */
    public function createAction(ApiContext $context): Response
    {
        /** @var CategoryWrittenEvent $createEvent */
        $createEvent = $this->categoryRepository->create($context->getPayload(), $context->getShopContext()->getTranslationContext());

        $response = [
            'data' => $this->categoryRepository->read($createEvent->getCategoryUuids(), $context->getShopContext()->getTranslationContext()),
            'errors' => $createEvent->getErrors()
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/category.{responseFormat}", name="api.category.upsert", methods={"PUT"})
     */
    public function upsertAction(ApiContext $context): Response
    {
        /** @var CategoryWrittenEvent $createEvent */
        $createEvent = $this->categoryRepository->upsert($context->getPayload(), $context->getShopContext()->getTranslationContext());

        $response = [
            'data' => $this->categoryRepository->read($createEvent->getCategoryUuids(), $context->getShopContext()->getTranslationContext()),
            'errors' => $createEvent->getErrors()
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/category.{responseFormat}", name="api.category.update", methods={"PATCH"})
     */
    public function updateAction(ApiContext $context): Response
    {
        /** @var CategoryWrittenEvent $createEvent */
        $createEvent = $this->categoryRepository->update($context->getPayload(), $context->getShopContext()->getTranslationContext());

        $response = [
            'data' => $this->categoryRepository->read($createEvent->getCategoryUuids(), $context->getShopContext()->getTranslationContext()),
            'errors' => $createEvent->getErrors()
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/category/{categoryUuid}.{responseFormat}", name="api.category.single_update", methods={"PATCH"})
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('categoryUuid');

        $updateEvent = $this->categoryRepository->update([$payload], $context->getShopContext()->getTranslationContext());

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        return $this->createResponse(
            ['data' => $this->categoryRepository->read([$payload['uuid']], $context->getShopContext()->getTranslationContext())->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/category.{responseFormat}", name="api.category.delete", methods={"DELETE"})
     */
    public function deleteAction(ApiContext $context): Response
    {
        $result = [];
        foreach ($context->getPayload() as $category) {
        }

        return $this->createResponse($result, $context);
    }
}
