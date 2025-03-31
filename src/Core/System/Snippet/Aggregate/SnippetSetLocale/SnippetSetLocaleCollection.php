<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Aggregate\SnippetSetLocale;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<SnippetSetLocaleEntity>
 */
#[Package('services-settings')]
class SnippetSetLocaleCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'snippet_set_locale_collection';
    }

    protected function getExpectedClass(): string
    {
        return SnippetSetLocaleEntity::class;
    }
}
