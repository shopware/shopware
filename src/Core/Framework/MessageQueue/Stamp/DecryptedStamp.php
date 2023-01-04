<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Stamp;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * @deprecated tag:v6.5.0 - reason:remove-decorator - will be removed, as we remove queue encryption
 */
#[Package('core')]
class DecryptedStamp implements StampInterface
{
}
