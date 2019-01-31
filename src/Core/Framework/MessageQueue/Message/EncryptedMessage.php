<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Message;

class EncryptedMessage
{
    /**
     * @var string
     */
    private $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
