<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class IndexingController extends AbstractController
{
    /**
     * @var EntityIndexerRegistry
     */
    private $registry;

    private MessageBusInterface $messageBus;

    public function __construct(EntityIndexerRegistry $registry, MessageBusInterface $messageBus)
    {
        $this->registry = $registry;
        $this->messageBus = $messageBus;
    }

    /**
     * @Since("6.0.0.0")
     * @OA\Post(
     *     path="/_action/indexing",
     *     summary="Run indexer",
     *     description="Runs all registered indexer in the shop asynchronously.",
     *     operationId="indexing",
     *     tags={"Admin API", "System Operations"},
     *     @OA\Response(
     *         response="200",
     *         description="Returns an empty response indicating that the indexing process started."
     *     )
     * )
     * @Route("/api/_action/indexing", name="api.action.indexing", methods={"POST"})
     */
    public function indexing(Request $request): JsonResponse
    {
        $this->registry->sendIndexingMessage();

        return new JsonResponse();
    }

    /**
     * @Since("6.4.0.0")
     * @OA\Post(
     *     path="/_action/indexing/{indexer}",
     *     summary="Iterate an indexer",
     *     description="Starts a defined indexer with an offset.

for the next request. `finish: true` in the response indicates that the indexer is finished",
     *     operationId="iterate",
     *     tags={"Admin API", "System Operations"},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="offset",
     *                 description="The offset for the iteration.",
     *                 type="integer"
     *             )
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="indexer",
     *         description="Name of the indexer to iterate.",
     *         @OA\Schema(type="string", pattern="^[0-9a-f]{32}$"),
     *         in="path",
     *         required=true
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns information about the iteration.",
     *         @OA\JsonContent(
     *               @OA\Property(
     *                  property="finish",
     *                  description="Indicates if the indexing process finished.",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="offset",
     *                  description="Offset to be used for the next iteration.",
     *                  type="integer"
     *              )
     *         )
     *     )
     * )
     *
     * @Route("/api/_action/indexing/{indexer}", name="api.action.indexing.iterate", methods={"POST"})
     */
    public function iterate(string $indexer, Request $request): JsonResponse
    {
        if (!$request->request->has('offset')) {
            throw new BadRequestHttpException('Parameter `offset` missing');
        }

        $indexer = $this->registry->getIndexer($indexer);

        $offset = ['offset' => $request->get('offset')];
        $message = $indexer ? $indexer->iterate($offset) : null;

        if ($message === null) {
            return new JsonResponse(['finish' => true]);
        }

        if ($indexer) {
            $indexer->handle($message);
        }

        return new JsonResponse(['finish' => false, 'offset' => $message->getOffset()]);
    }

    /**
     * @Since("6.4.2.1")
     * @OA\Post(
     *     path="/_action/indexing/products",
     *     summary="Send product indexing message",
     *     description="Dispachtes an product indexing message to the message bus, with the provided ids",
     *     operationId="indexing",
     *     tags={"Admin API", "System Operations"},
     *     @OA\Response(
     *         response="204",
     *         description="Returns an empty response indicating that the message dispatched."
     *     )
     * )
     * @Route("/api/_action/index-products", name="api.action.indexing.products", methods={"POST"})
     */
    public function products(Request $request): JsonResponse
    {
        if (!$request->request->has('ids')) {
            throw new BadRequestHttpException('Parameter `ids` missing');
        }

        $ids = $request->request->all('ids');

        if (empty($ids)) {
            throw new BadRequestHttpException('Parameter `ids` is no array or empty');
        }

        $message = new ProductIndexingMessage($ids, null);
        $message->setIndexer('product.indexer');

        $this->messageBus->dispatch($message);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
