<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionService;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Drafts of a content layout, which are branches of its revision graph, see {@see LayoutRevisionService}. A draft
 * is edited through the persisted mutation routes with `draftId` in the body.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ContentLayoutDraftController
{
    private const ID_PATTERN = '[0-9a-f]{32}';

    /**
     * @internal
     */
    public function __construct(
        private readonly LayoutRevisionService $revisionService,
        private readonly StoredTreeCodec $treeCodec,
    ) {
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/drafts', name: 'api.action.content_system.layout.draft.list', requirements: ['layoutId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_GET])]
    public function listDrafts(string $layoutId): JsonResponse
    {
        return new JsonResponse([
            'drafts' => array_map(LayoutDraftNormalizer::draft(...), $this->revisionService->listBranches($layoutId)),
        ]);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/drafts/{draftId}', name: 'api.action.content_system.layout.draft.detail', requirements: ['layoutId' => self::ID_PATTERN, 'draftId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_GET])]
    public function getDraft(string $layoutId, string $draftId): JsonResponse
    {
        $result = $this->revisionService->getBranch($layoutId, $draftId);

        return new JsonResponse([
            'draft' => LayoutDraftNormalizer::draft($result['branch']),
            'layout' => $this->treeCodec->encode(new StoredTree($result['layout'])),
        ]);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/create-draft', name: 'api.action.content_system.layout.draft.create', requirements: ['layoutId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function createDraft(string $layoutId, RequestDataBag $data): JsonResponse
    {
        $branch = $this->revisionService->createBranch(
            $layoutId,
            $this->string($data, 'name'),
            $this->string($data, 'fromRevisionId'),
        );

        return new JsonResponse(['draft' => LayoutDraftNormalizer::draft($branch)]);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/discard-draft', name: 'api.action.content_system.layout.draft.discard', requirements: ['layoutId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function discardDraft(string $layoutId, RequestDataBag $data): Response
    {
        $this->revisionService->deleteBranch($layoutId, $this->required($data, 'draftId'));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/publish-draft', name: 'api.action.content_system.layout.draft.publish', requirements: ['layoutId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function publishDraft(string $layoutId, RequestDataBag $data, Context $context): Response
    {
        $this->revisionService->publish(
            $layoutId,
            $this->required($data, 'draftId'),
            $this->required($data, 'expectedHead'),
            $context,
        );

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * A non-string value reads as absent.
     */
    private function string(RequestDataBag $data, string $key): ?string
    {
        $value = $data->get($key);

        return \is_string($value) ? $value : null;
    }

    private function required(RequestDataBag $data, string $key): string
    {
        return $this->string($data, $key) ?? throw ContentSystemException::missingRequestField([$key]);
    }
}
