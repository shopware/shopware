<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
abstract class AbstractProductStreamBuilder
{
    final public const STATE_DISPLAY_AS_GROUP_DISABLED = 'PRODUCT_STREAM_DISPLAY_AS_GROUP_DISABLED';

    abstract public function enrichCriteria(Criteria $criteria, string $id, Context $context): void;
}
