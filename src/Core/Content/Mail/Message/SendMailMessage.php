<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Message;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class SendMailMessage
{
    /**
     * @internal
     */
    public function __construct(public readonly string $mailDataPath)
    {
    }
}
