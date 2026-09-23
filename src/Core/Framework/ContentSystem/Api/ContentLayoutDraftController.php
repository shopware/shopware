<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A layout's draft is a DAL version of its content_layout row; a layout can have several.
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
     *
     * @param EntityRepository<ContentLayoutCollection> $contentLayoutRepository
     * @param EntityRepository<VersionCollection> $versionRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $contentLayoutRepository,
        private readonly EntityRepository $versionRepository,
    ) {
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/drafts', name: 'api.action.content_system.layout.draft.list', requirements: ['layoutId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:read']], methods: [Request::METHOD_GET])]
    public function list(string $layoutId): JsonResponse
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(version_id)) AS versionId, created_at AS createdAt, updated_at AS updatedAt
             FROM content_layout
             WHERE id = :id AND version_id != :live
             ORDER BY created_at DESC',
            ['id' => Uuid::fromHexToBytes($layoutId), 'live' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
        );

        $drafts = array_map(static fn (array $row): array => [
            'versionId' => $row['versionId'],
            'createdAt' => self::toIso($row['createdAt']),
            'updatedAt' => self::toIso($row['updatedAt']),
        ], $rows);

        return new JsonResponse(['drafts' => $drafts]);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/draft', name: 'api.action.content_system.layout.draft.create', requirements: ['layoutId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function create(string $layoutId, Context $context): JsonResponse
    {
        if (!$this->liveLayoutExists($layoutId)) {
            throw ContentSystemException::contentLayoutNotFound($layoutId);
        }

        // Drafts always branch from live, whatever sw-version-id the request carries.
        $versionId = $this->contentLayoutRepository->createVersion($layoutId, $context->createWithVersionId(Defaults::LIVE_VERSION));

        return new JsonResponse(['versionId' => $versionId]);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/draft/{versionId}/publish', name: 'api.action.content_system.layout.draft.publish', requirements: ['layoutId' => self::ID_PATTERN, 'versionId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_POST])]
    public function publish(string $layoutId, string $versionId, Context $context): Response
    {
        $this->assertIsDraftOf($layoutId, $versionId);

        // Merge replays the draft's write-protected updatedAt onto the live row, which only the system scope may write.
        // It merges into the context's version, so a draft sw-version-id header must not leak in.
        $liveContext = $context->createWithVersionId(Defaults::LIVE_VERSION);
        $liveContext->scope(Context::SYSTEM_SCOPE, fn (Context $systemContext) => $this->contentLayoutRepository->merge($versionId, $systemContext));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/content-system/layout/{layoutId}/draft/{versionId}', name: 'api.action.content_system.layout.draft.discard', requirements: ['layoutId' => self::ID_PATTERN, 'versionId' => self::ID_PATTERN], defaults: [PlatformRequest::ATTRIBUTE_ACL => ['content_layout:update']], methods: [Request::METHOD_DELETE])]
    public function discard(string $layoutId, string $versionId, Context $context): Response
    {
        $this->assertIsDraftOf($layoutId, $versionId);

        // The route is gated on content_layout:update, so the delete privileges on content_layout and version are
        // deliberately not required of the caller.
        $context->scope(Context::SYSTEM_SCOPE, function (Context $systemContext) use ($layoutId, $versionId): void {
            $this->contentLayoutRepository->delete([['id' => $layoutId]], $systemContext->createWithVersionId($versionId));
            $this->versionRepository->delete([['id' => $versionId]], $systemContext);
        });

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function isDraftOf(string $layoutId, string $versionId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM content_layout WHERE id = :id AND version_id = :version AND version_id != :live',
            [
                'id' => Uuid::fromHexToBytes($layoutId),
                'version' => Uuid::fromHexToBytes($versionId),
                'live' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
        );
    }

    private static function toIso(mixed $dateTime): ?string
    {
        if (!\is_string($dateTime)) {
            return null;
        }

        return (new \DateTimeImmutable($dateTime, new \DateTimeZone('UTC')))->format(\DATE_ATOM);
    }

    private function liveLayoutExists(string $layoutId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM content_layout WHERE id = :id AND version_id = :live',
            ['id' => Uuid::fromHexToBytes($layoutId), 'live' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
        );
    }

    private function assertIsDraftOf(string $layoutId, string $versionId): void
    {
        if (!$this->isDraftOf($layoutId, $versionId)) {
            throw ContentSystemException::contentLayoutDraftNotFound($layoutId, $versionId);
        }
    }
}
