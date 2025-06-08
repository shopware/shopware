<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductStreamIndexingMessage extends EntityIndexingMessage
{
}
