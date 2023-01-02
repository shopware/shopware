<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Message;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.5.0 - reason:remove-decorator - will be removed, as we remove queue encryption
 */
#[Package('core')]
class EncryptedMessage
{
    /**
     * @var string
     */
    private $message;

    /**
     * @internal
     */
    public function __construct(string $message)
    {
        $this->message = $message;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
