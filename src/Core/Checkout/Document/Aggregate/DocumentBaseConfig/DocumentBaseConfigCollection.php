<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                          add(DocumentBaseConfigEntity $entity)
 * @method void                          set(string $key, DocumentBaseConfigEntity $entity)
 * @method DocumentBaseConfigEntity[]    getIterator()
 * @method DocumentBaseConfigEntity[]    getElements()
 * @method DocumentBaseConfigEntity|null get(string $key)
 * @method DocumentBaseConfigEntity|null first()
 * @method DocumentBaseConfigEntity|null last()
 */
class DocumentBaseConfigCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return DocumentBaseConfigEntity::class;
    }
}
