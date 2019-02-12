<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Message;

class RetryMessage
{
    /**
     * @var string
     */
    private $deadMessageId;

    public function __construct(string $deadMessageId)
    {
        $this->deadMessageId = $deadMessageId;
    }

    public function getDeadMessageId(): string
    {
        return $this->deadMessageId;
    }
}
