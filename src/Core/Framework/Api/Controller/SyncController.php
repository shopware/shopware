<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
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
     * @var SyncService
     */
    private $syncService;

    public function __construct(SyncService $syncService, Serializer $serializer)
    {
        $this->serializer = $serializer;
        $this->syncService = $syncService;
    }

    /**
     * Starts a sync process for the list of provided actions.
     * This can be inserts, upserts, updates and deletes on different entities.
     * To continue upcoming actions on errors, please provide a "fail-on-error" header with value FALSE.
     *
     * @Route("/api/v{version}/_action/sync", name="api.action.sync", methods={"POST"})
     *
     * @throws \Throwable
     */
    public function sync(Request $request, Context $context, int $version): JsonResponse
    {
        // depending on the request header setting, we either
        // fail immediately or add any unexpected errors to our exception list
        /** @var bool $failOnError */
        $failOnError = filter_var($request->headers->get('fail-on-error', 'true'), FILTER_VALIDATE_BOOLEAN);

        $behavior = new SyncBehavior($failOnError);

        $payload = $this->serializer->decode($request->getContent(), 'json');

        $operations = [];
        foreach ($payload as $key => $operation) {
            $operations[] = new SyncOperation((string) $key, $operation['entity'], $operation['action'], $operation['payload'], $version);
        }

        $result = $this->syncService->sync($operations, $context, $behavior);

        if ($failOnError === true && !$result->isSuccess()) {
            return new JsonResponse($result, 400);
        }

        return new JsonResponse($result, 200);
    }
}
