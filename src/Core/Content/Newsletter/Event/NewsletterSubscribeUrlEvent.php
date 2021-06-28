<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\Event;

use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class NewsletterSubscribeUrlEvent extends Event implements SalesChannelAware, ShopwareSalesChannelEvent
{
    private string $subscribeUrl;

    private SalesChannelContext $salesChannelContext;

    private string $hashedEmail;

    private string $hash;

    private array $data;

    private NewsletterRecipientEntity $recipient;

    public function __construct(
        SalesChannelContext $salesChannelContext,
        string $subscribeUrl,
        string $hashedEmail,
        string $hash,
        array $data,
        NewsletterRecipientEntity $recipient
    ) {
        $this->subscribeUrl = $subscribeUrl;
        $this->salesChannelContext = $salesChannelContext;
        $this->hashedEmail = $hashedEmail;
        $this->hash = $hash;
        $this->data = $data;
        $this->recipient = $recipient;
    }

    public function getSubscribeUrl(): string
    {
        return $this->subscribeUrl;
    }

    public function setSubscribeUrl(string $subscribeUrl): void
    {
        $this->subscribeUrl = $subscribeUrl;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelContext->getSalesChannel()->getId();
    }

    public function getHashedEmail(): string
    {
        return $this->hashedEmail;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getRecipient(): NewsletterRecipientEntity
    {
        return $this->recipient;
    }
}
