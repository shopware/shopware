<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Message;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @codeCoverageIgnore
 */
#[Package('services-settings')]
class SendMailMessage implements AsyncMessageInterface
{
    /**
     * @internal
     */
    public function __construct(public readonly string $mailDataPath)
    {
    }
}
