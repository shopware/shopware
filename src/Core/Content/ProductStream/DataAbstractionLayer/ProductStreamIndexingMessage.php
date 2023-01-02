<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;

/**
 * @package business-ops
 */
#[Package('business-ops')]
class ProductStreamIndexingMessage extends EntityIndexingMessage
{
}
