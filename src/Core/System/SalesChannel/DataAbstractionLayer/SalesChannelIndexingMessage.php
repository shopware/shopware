<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;

/**
 * @package sales-channel
 */
#[Package('sales-channel')]
class SalesChannelIndexingMessage extends EntityIndexingMessage
{
}
