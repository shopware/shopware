<?php declare(strict_types=1);

namespace Shopware\ProductVote\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\ProductVote\Repository\ProductVoteRepository;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.product_vote.api_controller", path="/api")
 */
class ProductVoteController extends ApiController
{
    /**
     * @var ProductVoteRepository
     */
    private $productVoteRepository;

    public function __construct(ProductVoteRepository $productVoteRepository)
    {
        $this->productVoteRepository = $productVoteRepository;
    }

    public function getXmlRootKey(): string
    {
        return 'productVotes';
    }

    public function getXmlChildKey(): string
    {
        return 'productVote';
    }

    /**
     * @Route("/productVote.{responseFormat}", name="api.productVote.list", methods={"GET"})
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

        $productVotes = $this->productVoteRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $productVotes, 'total' => $productVotes->getTotal()],
            $context
        );
    }

    /**
     * @Route("/productVote/{productVoteUuid}.{responseFormat}", name="api.productVote.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('productVoteUuid');
        $productVotes = $this->productVoteRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(['data' => $productVotes->get($uuid)], $context);
    }

    /**
     * @Route("/productVote.{responseFormat}", name="api.productVote.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->productVoteRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productVotes = $this->productVoteRepository->read(
            $createEvent->getProductVoteUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productVotes,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productVote.{responseFormat}", name="api.productVote.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->productVoteRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productVotes = $this->productVoteRepository->read(
            $createEvent->getProductVoteUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productVotes,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productVote.{responseFormat}", name="api.productVote.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->productVoteRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $productVotes = $this->productVoteRepository->read(
            $createEvent->getProductVoteUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $productVotes,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/productVote/{productVoteUuid}.{responseFormat}", name="api.productVote.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('productVoteUuid');

        $updateEvent = $this->productVoteRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $productVotes = $this->productVoteRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $productVotes->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/productVote.{responseFormat}", name="api.productVote.delete", methods={"DELETE"})
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
