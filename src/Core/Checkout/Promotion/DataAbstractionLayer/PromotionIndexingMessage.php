<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
class PromotionIndexingMessage extends EntityIndexingMessage
{
}
