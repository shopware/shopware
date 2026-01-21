<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Temporary buffered flow that can be converted into
 * a {@see StorableFlow} by the {@see FlowFactory}.
 *
 * @interal
 *
 * @final
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class BufferedFlow extends Struct
{
    /**
     * @param array<string, mixed> $stored
     */
    public function __construct(
        protected string $eventName,
        protected Context $eventContext,
        protected array $stored,
    ) {
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function setEventName(string $eventName): void
    {
        $this->eventName = $eventName;
    }

    public function getEventContext(): Context
    {
        return $this->eventContext;
    }

    public function setEventContext(Context $eventContext): void
    {
        $this->eventContext = $eventContext;
    }

    /**
     * @return array<string, mixed>
     */
    public function getStored(): array
    {
        return $this->stored;
    }

    /**
     * @param array<string, mixed> $stored
     */
    public function setStored(array $stored): void
    {
        $this->stored = $stored;
    }
}
