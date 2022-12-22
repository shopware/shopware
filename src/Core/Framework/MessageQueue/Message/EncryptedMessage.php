<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Message;

/**
 * @package core
 *
 * @deprecated tag:v6.5.0 - reason:remove-decorator - will be removed, as we remove queue encryption
 */
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
