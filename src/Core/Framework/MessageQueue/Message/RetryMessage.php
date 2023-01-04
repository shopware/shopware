<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Message;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.5.0 - reason:remove-decorator - will be removed, as we use default symfony retry mechanism
 */
#[Package('core')]
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
