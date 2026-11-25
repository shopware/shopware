<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutBranch;
use Shopware\Core\Framework\Log\Package;

/**
 * The wire shape of a draft, shared by the draft routes and the draft mutation routes.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\ContentSystem\Api\ContentLayoutDraftControllerTest
 */
#[Package('framework')]
final class LayoutDraftNormalizer
{
    /**
     * @return array{id: string, name: string, base: string, head: string, createdAt: string, updatedAt: string}
     */
    public static function draft(LayoutBranch $branch): array
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
}
