<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * @package core
 *
 * @deprecated tag:v6.5.0 - reason:remove-decorator - will be removed, as we remove queue encryption
 */
class DecryptedStamp implements StampInterface
{
}
