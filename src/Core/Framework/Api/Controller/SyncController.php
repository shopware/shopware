<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Doctrine\DBAL\ConnectionException;
use Shopware\Core\Framework\Api\Exception\InvalidSyncOperationException;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncResult;
use Shopware\Core\Framework\Api\Sync\SyncServiceInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

/**
 * @Route(defaults={"_routeScope"={"api"}})
 */
class SyncController extends AbstractController
{
    public const ACTION_UPSERT = 'upsert';
    public const ACTION_DELETE = 'delete';

    private DecoderInterface $serializer;

    private SyncServiceInterface $syncService;

    /**
     * @internal
     */
    public function __construct(SyncServiceInterface $syncService, DecoderInterface $serializer)
    {
        $this->serializer = $serializer;
        $this->syncService = $syncService;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/sync", name="api.action.sync", methods={"POST"})
     *
     * @throws ConnectionException
     * @throws InvalidSyncOperationException
     */
    public function sync(Request $request, Context $context): JsonResponse
    {
        $indexingSkips = array_filter(explode(',', $request->headers->get(PlatformRequest::HEADER_INDEXING_SKIP, '')));

        if (Feature::isActive('FEATURE_NEXT_15815')) {
            $behavior = new SyncBehavior(
                $request->headers->get(PlatformRequest::HEADER_INDEXING_BEHAVIOR),
                $indexingSkips
            );
        } else {
            $behavior = new SyncBehavior(
                filter_var($request->headers->get(PlatformRequest::HEADER_FAIL_ON_ERROR, 'true'), \FILTER_VALIDATE_BOOLEAN),
                filter_var($request->headers->get(PlatformRequest::HEADER_SINGLE_OPERATION, 'false'), \FILTER_VALIDATE_BOOLEAN),
                $request->headers->get(PlatformRequest::HEADER_INDEXING_BEHAVIOR, null),
                $indexingSkips
            );
        }

        $payload = $this->serializer->decode($request->getContent(), 'json');

        $operations = [];
        foreach ($payload as $key => $operation) {
            if (isset($operation['key'])) {
                $key = $operation['key'];
            }
            $operations[] = new SyncOperation((string) $key, $operation['entity'], $operation['action'], $operation['payload']);
        }

        $result = $context->scope(Context::CRUD_API_SCOPE, function (Context $context) use ($operations, $behavior): SyncResult {
            return $this->syncService->sync($operations, $context, $behavior);
        });

        if (Feature::isActive('FEATURE_NEXT_15815')) {
            return $this->createResponse($result, Response::HTTP_OK);
        }

        if ($behavior->failOnError() && !$result->isSuccess()) {
            return $this->createResponse($result, Response::HTTP_BAD_REQUEST);
        }

        return $this->createResponse($result, Response::HTTP_OK);
    }

    private function createResponse(SyncResult $result, int $statusCode = 200): JsonResponse
    {
        $response = new JsonResponse(null, $statusCode);
        $response->setEncodingOptions(JsonResponse::DEFAULT_ENCODING_OPTIONS | \JSON_INVALID_UTF8_SUBSTITUTE);
        $response->setData($result);

        return $response;
    }
}
