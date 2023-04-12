<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
abstract class AbstractProductStreamUpdater extends EntityIndexer
{
    /**
     * @param array<string> $ids
     */
    abstract public function updateProducts(array $ids, Context $context): void;
}
