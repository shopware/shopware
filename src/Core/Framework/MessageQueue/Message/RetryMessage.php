<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Message;

/**
 * @package core
 *
 * @deprecated tag:v6.5.0 - reason:remove-decorator - will be removed, as we use default symfony retry mechanism
 */
class RetryMessage
{
    /**
     * @var string
     */
    private $deadMessageId;

    /**
     * @internal
     */
    public function __construct(string $deadMessageId)
    {
        $this->deadMessageId = $deadMessageId;
    }

    public function getDeadMessageId(): string
    {
        return $this->deadMessageId;
    }
}
