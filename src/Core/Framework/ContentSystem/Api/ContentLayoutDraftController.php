<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionService;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

/**
 * Drafts of a content layout, named pointers into its revision graph, see {@see LayoutRevisionService}, and the
 * published revisions a draft can be opened from. A draft is edited through the persisted mutation routes with
 * `draftId` in the body.
 *
 * @internal
 *
 * @final
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\ContentSystem\Api\ContentLayoutDraftControllerTest
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
readonly class ContentLayoutDraftController
{
    /**
     * @internal
     */
    public function __construct(
        private LayoutRevisionService $revisionService,
        private StoredTreeCodec $treeCodec,
    ) {
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/drafts', name: 'api.action.content_system.layout.draft.list', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_GET])]
    public function listDrafts(string $layoutId): JsonResponse
    {
        return new JsonResponse([
            'drafts' => array_map(LayoutDraftNormalizer::draft(...), $this->revisionService->listDrafts($layoutId)),
        ]);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/drafts/{draftId}', name: 'api.action.content_system.layout.draft.detail', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_GET])]
    public function getDraft(string $layoutId, string $draftId): JsonResponse
    {
        $result = $this->revisionService->getDraft($layoutId, $draftId);

        return new JsonResponse([
            'draft' => LayoutDraftNormalizer::draft($result['draft']),
            'layout' => $this->treeCodec->encode(new StoredTree($result['layout'])),
        ]);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/published-revisions', name: 'api.action.content_system.layout.revision.published', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_GET])]
    public function listPublishedRevisions(string $layoutId): JsonResponse
    {
        return new JsonResponse([
            'revisions' => array_map(
                static fn (array $published): array => [
                    'id' => $published['revision']->id,
                    'live' => $published['live'],
                    'createdAt' => $published['revision']->createdAt->format(\DATE_ATOM),
                    'createdById' => $published['revision']->createdBy,
                    'publishedAt' => $published['revision']->publishedAt?->format(\DATE_ATOM),
                    'publishedById' => $published['revision']->publishedBy,
                    'lastLiveAt' => $published['revision']->lastLiveAt?->format(\DATE_ATOM),
                ],
                $this->revisionService->publishedRevisions($layoutId),
            ),
        ]);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/create-draft', name: 'api.action.content_system.layout.draft.create', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function createDraft(
        string $layoutId,
        Context $context,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ?ContentLayoutCreateDraftRequest $payload = null,
    ): JsonResponse {
        $draft = $this->revisionService->createDraft($layoutId, $context, $payload?->name, $payload?->fromRevisionId);

        return new JsonResponse(['draft' => LayoutDraftNormalizer::draft($draft)]);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/discard-draft', name: 'api.action.content_system.layout.draft.discard', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function discardDraft(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutDiscardDraftRequest $payload,
    ): Response {
        $this->revisionService->discardDraft($layoutId, $payload->draftId);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/publish-draft', name: 'api.action.content_system.layout.draft.publish', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function publishDraft(
        string $layoutId,
        #[MapRequestPayload(serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ContentLayoutPublishDraftRequest $payload,
        Context $context,
    ): Response {
        $this->revisionService->publish($layoutId, $payload->draftId, $payload->expectedHeadRevisionId, $context);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
