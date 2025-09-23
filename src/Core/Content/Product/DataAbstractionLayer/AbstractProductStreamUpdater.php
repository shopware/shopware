<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractProductStreamUpdater extends EntityIndexer
{
    /**
     * @param array<string> $ids
     * @param array<string> $affectedFields
     */
    abstract public function updateProducts(array $ids, Context $context, array $affectedFields = []): void;

    /**
     * @param array<string, array<string>> $fieldsChangeSet
     *
     * @return array<string>
     */
    public function getAffectedFilterFields(array $fieldsChangeSet): array
    {
        return [];
    }
}
