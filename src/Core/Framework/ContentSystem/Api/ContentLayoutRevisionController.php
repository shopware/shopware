<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutBranch;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevision;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionService;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\RevisionGraph;
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
 * Drafts of a content layout as branches of its revision graph, see {@see LayoutRevisionService}.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ContentLayoutRevisionController
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

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/branches', name: 'api.action.content_system.layout.branch.list', requirements: ['layoutId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_GET])]
    public function listBranches(string $layoutId): JsonResponse
    {
        return new JsonResponse([
            'branches' => array_map($this->branch(...), $this->revisionService->listBranches($layoutId)),
        ]);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/branches', name: 'api.action.content_system.layout.branch.create', requirements: ['layoutId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function createBranch(string $layoutId, RequestDataBag $data): JsonResponse
    {
        $branch = $this->revisionService->createBranch(
            $layoutId,
            $this->string($data, 'name'),
            $this->string($data, 'fromRevisionId'),
        );

        return new JsonResponse(['branch' => $this->branch($branch)]);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/branches/{branchId}', name: 'api.action.content_system.layout.branch.detail', requirements: ['layoutId' => self::ID_PATTERN, 'branchId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_GET])]
    public function getBranch(string $layoutId, string $branchId): JsonResponse
    {
        $result = $this->revisionService->getBranch($layoutId, $branchId);

        return new JsonResponse([
            'branch' => $this->branch($result['branch']),
            'layout' => $this->tree($result['head']),
        ]);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/branches/{branchId}', name: 'api.action.content_system.layout.branch.delete', requirements: ['layoutId' => self::ID_PATTERN, 'branchId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_DELETE])]
    public function deleteBranch(string $layoutId, string $branchId): Response
    {
        $this->revisionService->deleteBranch($layoutId, $branchId);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/branches/{branchId}/revisions', name: 'api.action.content_system.layout.revision.create', requirements: ['layoutId' => self::ID_PATTERN, 'branchId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function saveRevision(string $layoutId, string $branchId, RequestDataBag $data, Context $context): JsonResponse
    {
        $result = $this->revisionService->saveRevision(
            $layoutId,
            $branchId,
            $data->all('layout'),
            $this->string($data, 'expectedHead') ?? '',
            $this->string($data, 'name'),
            $context,
        );

        return new JsonResponse([
            'revision' => $this->revisionSummary($result['graph'], $result['revision']),
            'branch' => $this->branch($result['branch']),
            'layout' => $this->tree($result['revision']),
        ]);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/revisions', name: 'api.action.content_system.layout.revision.list', requirements: ['layoutId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_GET])]
    public function listRevisions(string $layoutId): JsonResponse
    {
        $graph = $this->revisionService->listRevisions($layoutId);

        return new JsonResponse([
            'published' => $graph->published,
            'revisions' => array_map(
                fn (LayoutRevision $revision): array => $this->revisionSummary($graph, $revision),
                $graph->newestFirst(),
            ),
        ]);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/publish', name: 'api.action.content_system.layout.revision.publish', requirements: ['layoutId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function publish(string $layoutId, RequestDataBag $data, Context $context): Response
    {
        $this->revisionService->publish(
            $layoutId,
            $this->string($data, 'revisionId') ?? '',
            $this->string($data, 'deleteBranchId'),
            $context,
        );

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array{id: string, name: string, base: string, head: string, createdAt: string, updatedAt: string}
     */
    private function branch(LayoutBranch $branch): array
    {
        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'base' => $branch->base,
            'head' => $branch->head,
            'createdAt' => $branch->createdAt->format(\DATE_ATOM),
            'updatedAt' => $branch->updatedAt->format(\DATE_ATOM),
        ];
    }

    /**
     * @return array{id: string, parent: string|null, createdAt: string, createdBy: string|null, branches: list<string>, published: bool}
     */
    private function revisionSummary(RevisionGraph $graph, LayoutRevision $revision): array
    {
        return [
            'id' => $revision->id,
            'parent' => $revision->parent,
            'createdAt' => $revision->createdAt->format(\DATE_ATOM),
            'createdBy' => $revision->createdBy,
            'branches' => $graph->branchIdsAt($revision->id),
            'published' => $revision->id === $graph->published,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tree(LayoutRevision $revision): array
    {
        return $this->treeCodec->encode(new StoredTree($revision->tree));
    }

    /**
     * A missing or non-string value reads as absent. Where the service needs one — `expectedHead`, `revisionId` —
     * the empty string it gets instead matches no head and no revision, so the request fails as a 409 or a 404.
     */
    private function string(RequestDataBag $data, string $key): ?string
    {
        $value = $data->get($key);

        return \is_string($value) ? $value : null;
    }
}
