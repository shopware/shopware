<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class SalesChannelIndexingMessage extends EntityIndexingMessage
{
}
