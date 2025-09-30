<?php declare(strict_types=1);

namespace Shopware\Core\Service\Message;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
readonly class InstallServicesMessage implements AsyncMessageInterface
{
}
