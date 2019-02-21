<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ActionEvent;

class CustomerLoginEvent extends ActionEvent
{
    public const EVENT_NAME = 'checkout.customer.login';

    /**
     * @var CustomerEntity
     */
    private $customer;

    public function __construct(Context $context, CustomerEntity $customer)
    {
        parent::__construct($context);

        $this->customer = $customer;
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }
}
