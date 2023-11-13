<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Message;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

#[Package('core')]
class CleanupOldCacheFolders implements AsyncMessageInterface
{
}
