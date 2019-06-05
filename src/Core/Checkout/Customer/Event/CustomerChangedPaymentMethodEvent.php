<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Contracts\EventDispatcher\Event;

class CustomerChangedPaymentMethodEvent extends Event implements BusinessEventInterface
{
    public const EVENT_NAME = 'checkout.customer.changed-payment-method';

    /**
     * @var CustomerEntity
     */
    private $customer;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var RequestDataBag
     */
    private $requestDataBag;

    /**
     * @var string
     */
    private $salesChannelId;

    public function __construct(Context $context, CustomerEntity $customer, RequestDataBag $requestDataBag, string $salesChannelId)
    {
        $this->customer = $customer;
        $this->context = $context;
        $this->requestDataBag = $requestDataBag;
        $this->salesChannelId = $salesChannelId;
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getRequestDataBag(): RequestDataBag
    {
        return $this->requestDataBag;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('customer', new EntityType(CustomerDefinition::class));
    }
}
