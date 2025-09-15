<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Events;

use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('after-sales')]
class MailRecipientsModifyEvent extends Event implements ShopwareEvent
{
    /**
     * @param array<int|string, string> $recipients
     * @param array<string, mixed> $eventConfig
     */
    public function __construct(
        private array $recipients,
        private readonly array $eventConfig,
        private readonly StorableFlow $storableFlow
    ) {
    }

    /**
     * @return array<int|string, string>
     */
    public function getRecipients(): array
    {
        return $this->recipients;
    }

    /**
     * @param array<int|string, string> $recipients
     */
    public function setRecipients(array $recipients): void
    {
        $this->recipients = $recipients;
    }

    /**
     * @return array<string, mixed>
     */
    public function getEventConfig(): array
    {
        return $this->eventConfig;
    }

    public function getStorableFlow(): StorableFlow
    {
        return $this->storableFlow;
    }

    public function addRecipient(string $email, string $name): void
    {
        $this->recipients[$email] = $name;
    }

    public function removeRecipient(string $email): void
    {
        unset($this->recipients[$email]);
    }

    public function hasRecipient(string $email): bool
    {
        return \array_key_exists($email, $this->recipients);
    }

    public function getContext(): Context
    {
        return $this->getStorableFlow()->getContext();
    }
}
