<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Core\Application;

use Shopware\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopware\Core\Content\Media\Core\Params\ThumbnailLocationStruct;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal Just for abstraction between domain and infrastructure. No public API!
 */
#[Package('discovery')]
interface MediaLocationBuilder
{
    /**
     * @param list<string> $ids
     *
     * @return array<string, MediaLocationStruct> indexed by id
     */
    public function media(array $ids): array;

    /**
     * @param list<string> $ids
     *
     * @return array<string, ThumbnailLocationStruct> indexed by id
     */
    public function thumbnails(array $ids): array;
}
