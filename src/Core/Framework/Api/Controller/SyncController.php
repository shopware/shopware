<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncResult;
use Shopware\Core\Framework\Api\Sync\SyncServiceInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Serializer;

/**
 * @RouteScope(scopes={"api"})
 */
class SyncController extends AbstractController
{
    public const ACTION_UPSERT = 'upsert';
    public const ACTION_DELETE = 'delete';

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var SyncServiceInterface
     */
    private $syncService;

    public function __construct(SyncServiceInterface $syncService, Serializer $serializer)
    {
        $this->serializer = $serializer;
        $this->syncService = $syncService;
    }

    /**
     * @Since("6.0.0.0")
     * @OA\Post(
     *     path="/_action/sync",
     *     summary="Bulk edit entities",
     *     description="Starts a sync process for the list of provided actions. This can be inserts, upserts, updates and deletes on different entities.

to an asynchronous process in the background. You can control the behaviour with the `single-operation` and `indexing-behavior` header.",
     *     operationId="sync",
     *     tags={"Admin API", "Bulk Operations"},
     *     @OA\Parameter(
     *          name="fail-on-error",
     *          description="To continue upcoming actions on errors, set the `fail-on-error` header to `false`.",
     *          in="header",
     *          @OA\Schema(type="boolean", default=true)
     *     ),
     *     @OA\Parameter(
     *          name="single-operation",
     *          description="Controls weather the data is written at once or in seperate transactions.
- `true`: Data will be written in a single transaction",
     *          in="header",
     *          @OA\Schema(type="boolean", default=false)
     *     ),
     *     @OA\Parameter(
     *          name="indexing-behavior",
     *          description="Controls the indexing behavior.
- `disable-indexing`: Data indexing is completely disabled",
     *          in="header",
     *          @OA\Schema(type="string", enum={"use-queue-indexing", "disable-indexing"})
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                  type="object",
     *                  required={"action", "entity", "payload"},
     *                  @OA\Property(
     *                      property="action",
     *                      description="The action indicates what should happen with the provided payload.
* `upsert`: The Sync API does not differ between create and update operations,
but always performs an upsert operation. During an upsert, the system checks whether the entity already exists in the
system and updates it if an identifier has been passed, otherwise a new entity is created with this identifier.
* `delete`: Deletes entites with the provided identifiers",
     *                      type="string",
     *                      enum={"upsert", "delete"}
     *                  ),
     *                  @OA\Property(
     *                      property="entity",
     *                      description="The entity that should be processed with the payload.",
     *                      example="product",
     *                      type="string"
     *                  ),
     *                  @OA\Property(
     *                      property="payload",
     *                      description="Contains a list of changesets for an entity. If the action type is `delete`,
a list of identifiers can be provided.",
     *                      type="array",
     *                      @OA\Items(type="object")
     *                  ),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns a sync result containing information about the updated entities",
     *         @OA\JsonContent(
     *               @OA\Property(
     *                  property="data",
     *                  description="Object with information about the updated entites",
     *                  type="object"
     *              ),
     *              @OA\Property(
     *                  property="success",
     *                  description="Indicator if the sync was successful.",
     *                  type="boolean"
     *              )
     *         )
     *     )
     * )
     * @Route("/api/_action/sync", name="api.action.sync", methods={"POST"})
     *
     * @throws \Throwable
     */
    public function sync(Request $request, Context $context): JsonResponse
    {
        $behavior = new SyncBehavior(
            filter_var($request->headers->get(PlatformRequest::HEADER_FAIL_ON_ERROR, 'true'), \FILTER_VALIDATE_BOOLEAN),
            filter_var($request->headers->get(PlatformRequest::HEADER_SINGLE_OPERATION, 'false'), \FILTER_VALIDATE_BOOLEAN),
            $request->headers->get(PlatformRequest::HEADER_INDEXING_BEHAVIOR, null)
        );

        $payload = $this->serializer->decode($request->getContent(), 'json');

        $operations = [];
        foreach ($payload as $key => $operation) {
            $operations[] = new SyncOperation((string) $key, $operation['entity'], $operation['action'], $operation['payload']);
        }

        $result = $context->scope(Context::CRUD_API_SCOPE, function (Context $context) use ($operations, $behavior): SyncResult {
            return $this->syncService->sync($operations, $context, $behavior);
        });

        if ($behavior->failOnError() && !$result->isSuccess()) {
            return new JsonResponse($result, 400);
        }

        return new JsonResponse($result, 200);
    }
}
