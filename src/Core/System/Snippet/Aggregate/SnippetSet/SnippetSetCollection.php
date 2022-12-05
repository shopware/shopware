<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Aggregate\SnippetSet;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<SnippetSetEntity>
 *
 * @package system-settings
 */
class SnippetSetCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'snippet_set_collection';
    }

    protected function getExpectedClass(): string
    {
        return SnippetSetEntity::class;
    }
}
