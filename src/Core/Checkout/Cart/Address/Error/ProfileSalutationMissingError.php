<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Address\Error;

use Shopware\Core\Checkout\Cart\Error\ErrorRoute;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class ProfileSalutationMissingError extends SalutationMissingError
{
    protected const KEY = parent::KEY . '-profile';

    public function __construct(CustomerEntity $customer)
    {
        $this->message = sprintf(
            'A salutation needs to be defined for the customer profile %s, %s %s.',
            $customer->getCustomerNumber(),
            $customer->getFirstName(),
            $customer->getLastName()
        );

        $this->parameters = [
            'entityId' => $customer->getId(),
        ];

        parent::__construct($this->message);
    }

    public function getId(): string
    {
        return self::KEY;
    }

    public function getRoute(): ?ErrorRoute
    {
        return new ErrorRoute('frontend.account.profile.page');
    }
}
