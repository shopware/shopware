<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Log\Package;

/**
 * Excludes a technical media association from being treated as real media usage by `media:delete-unused`.
 */
#[Package('framework')]
class IgnoreInUnusedMediaSearch extends Flag
{
    public function parse(): \Generator
    {
        yield 'ignore_in_unused_media_search' => true;
    }
}
