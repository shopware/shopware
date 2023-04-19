<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Doctrine\DBAL\ConnectionException;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Exception\InvalidSyncOperationException;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncResult;
use Shopware\Core\Framework\Api\Sync\SyncServiceInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('core')]
class SyncController extends AbstractController
{
    final public const ACTION_UPSERT = 'upsert';
    final public const ACTION_DELETE = 'delete';

    /**
     * @internal
     */
    public function __construct(
        private readonly SyncServiceInterface $syncService,
        private readonly DecoderInterface $serializer
    ) {
    }

    /**
     * @throws ConnectionException
     * @throws InvalidSyncOperationException
     */
    #[Route(path: '/api/_action/sync', name: 'api.action.sync', methods: ['POST'])]
    public function sync(Request $request, Context $context): JsonResponse
    {
        /** @var list<string> $indexingSkips */
        $indexingSkips = array_filter(explode(',', (string) $request->headers->get(PlatformRequest::HEADER_INDEXING_SKIP, '')));

        $behavior = new SyncBehavior(
            $request->headers->get(PlatformRequest::HEADER_INDEXING_BEHAVIOR),
            $indexingSkips
        );

        $payload = $this->serializer->decode($request->getContent(), 'json');

        $operations = [];
        foreach ($payload as $key => $operation) {
            if (isset($operation['key'])) {
                $key = $operation['key'];
            }
            $key = (string) $key;
            $operations[] = new SyncOperation(
                $key,
                $operation['entity'],
                $operation['action'],
                $operation['payload'] ?? [],
                $operation['criteria'] ?? []
            );

            if (empty($operation['entity'])) {
                throw ApiException::invalidSyncOperationException(sprintf('Missing "entity" argument for operation with key "%s". It needs to be a non-empty string.', (string) $key));
            }
        }

        $result = $context->scope(Context::CRUD_API_SCOPE, fn (Context $context): SyncResult => $this->syncService->sync($operations, $context, $behavior));

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
