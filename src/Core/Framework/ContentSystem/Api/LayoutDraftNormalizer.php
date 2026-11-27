<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutDraft;
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
     * @return array{id: string, name: string, baseRevisionId: string, headRevisionId: string, liveAtOpenRevisionId: string, createdAt: string, createdById: string|null, updatedAt: string}
     */
    public static function draft(LayoutDraft $draft): array
    {
        return [
            'id' => $draft->id,
            'name' => $draft->name,
            'baseRevisionId' => $draft->base,
            'headRevisionId' => $draft->head,
            'liveAtOpenRevisionId' => $draft->liveAtOpen,
            'createdAt' => $draft->createdAt->format(\DATE_ATOM),
            'createdById' => $draft->createdBy,
            'updatedAt' => $draft->updatedAt->format(\DATE_ATOM),
        ];
    }
}
