<?php

declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class CustomerLoginFailureEvent extends Event implements BusinessEventInterface, SalesChannelAware, ShopwareSalesChannelEvent
{
    public const EVENT_NAME = 'checkout.customer.login.failure';

    protected string $customerEmail;

    protected SalesChannelContext $salesChannelContext;

    protected \Throwable $failureException;

    public function __construct(SalesChannelContext $salesChannelContext, string $customerEmail, \Throwable $failureException)
    {
        $this->customerEmail = $customerEmail;
        $this->salesChannelContext = $salesChannelContext;
        $this->failureException = $failureException;
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
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

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('customerEmail', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }

    public function getFailureException(): \Throwable
    {
        return $this->failureException;
    }
}
