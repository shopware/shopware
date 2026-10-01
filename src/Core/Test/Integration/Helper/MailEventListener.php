<?php declare(strict_types=1);

namespace Shopware\Core\Test\Integration\Helper;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Events\FlowSendMailActionEvent;

/**
 * @internal
 */
class MailEventListener
{
    /**
     * @var array<string, list<FlowSendMailActionEvent>>
     */
    private array $events = [];

    /**
     * @param array<string, string> $mapping
     */
    public function __construct(private readonly array $mapping)
    {
    }

    public function __invoke(FlowSendMailActionEvent $event): void
    {
        $mailTemplateTypeId = $event->getMailTemplate()->getMailTemplateTypeId();
        if ($mailTemplateTypeId === null) {
            return;
        }
        $name = $this->mapping[$mailTemplateTypeId];

        $this->events[$name][] = $event;
    }

    public function assertSent(string $type): void
    {
        TestCase::assertTrue($this->sent($type), \sprintf('Expected to send %s mail', $type));
    }

    public function sent(string $type): bool
    {
        return !empty($this->events[$type]);
    }

    /**
     * @return ($type is string ? list<FlowSendMailActionEvent> : array<string, list<FlowSendMailActionEvent>>)
     */
    public function get(?string $type = null): array
    {
        return $type !== null ? $this->events[$type] : $this->events;
    }
}
