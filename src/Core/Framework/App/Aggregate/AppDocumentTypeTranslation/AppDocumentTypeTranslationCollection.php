<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppDocumentTypeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<AppDocumentTypeTranslationEntity>
 */
#[Package('framework')]
class AppDocumentTypeTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'app_document_type_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return AppDocumentTypeTranslationEntity::class;
    }
}
