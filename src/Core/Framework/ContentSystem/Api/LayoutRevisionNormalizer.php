<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutBranch;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevision;
use Shopware\Core\Framework\Log\Package;

/**
 * The wire shape of branches and revisions, shared by the revision routes and the branch mutation routes.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\ContentSystem\Api\ContentLayoutRevisionControllerTest
 */
#[Package('framework')]
final class LayoutRevisionNormalizer
{
    /**
     * @return array{id: string, name: string, base: string, head: string, createdAt: string, updatedAt: string}
     */
    public static function branch(LayoutBranch $branch): array
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
     * @param list<string> $branchIds
     *
     * @return array{id: string, parent: string|null, createdAt: string, createdBy: string|null, branches: list<string>, published: bool}
     */
    public static function revision(LayoutRevision $revision, array $branchIds, bool $published): array
    {
        return [
            'id' => $revision->id,
            'parent' => $revision->parent,
            'createdAt' => $revision->createdAt->format(\DATE_ATOM),
            'createdBy' => $revision->createdBy,
            'branches' => $branchIds,
            'published' => $published,
        ];
    }
}
