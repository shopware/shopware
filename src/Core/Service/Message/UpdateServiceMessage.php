<?php declare(strict_types=1);

namespace Shopware\Core\Service\Message;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @internal
 */
#[Package('core')]
readonly class UpdateServiceMessage implements AsyncMessageInterface
{
    public function __construct(public string $name)
    {
    }
}
