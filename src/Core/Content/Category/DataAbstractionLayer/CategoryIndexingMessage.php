<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class CategoryIndexingMessage extends EntityIndexingMessage
{
}
