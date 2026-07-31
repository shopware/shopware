<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppDocumentType;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<AppDocumentTypeEntity>
 */
#[Package('framework')]
class AppDocumentTypeCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'app_document_type_collection';
    }

    protected function getExpectedClass(): string
    {
        return AppDocumentTypeEntity::class;
    }
}
