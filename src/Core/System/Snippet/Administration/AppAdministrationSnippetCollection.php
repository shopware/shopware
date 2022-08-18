<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Administration;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<AppAdministrationSnippetEntity>
 *
 * @method void                                add(AppAdministrationSnippetEntity $entity)
 * @method void                                set(string $key, AppAdministrationSnippetEntity $entity)
 * @method AppAdministrationSnippetEntity[]    getIterator()
 * @method AppAdministrationSnippetEntity[]    getElements()
 * @method AppAdministrationSnippetEntity|null get(string $key)
 * @method AppAdministrationSnippetEntity|null first()
 * @method AppAdministrationSnippetEntity|null last()
 */
class AppAdministrationSnippetCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'administration_snippet_collection';
    }

    protected function getExpectedClass(): string
    {
        return AppAdministrationSnippetEntity::class;
    }
}
