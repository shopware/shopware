<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Command;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class RegisterCustomerCommand extends AbstractContextGatewayCommand
{
    public const COMMAND_KEY = 'context_register-customer';

    //            'guest' => true,
    //            'storefrontUrl' => $this->getStorefrontUrl($salesChannelContext),
    //            'salutationId' => $salutationId,
    //            'email' => $paypal->getEmailAddress(),
    //            'firstName' => $paypal->getName()->getGivenName(),
    //            'lastName' => $paypal->getName()->getSurname(),
    //            'billingAddress' => $this->getAddressData($paypalOrder, $salesChannelContext->getContext(), $salutationId),
    //            'acceptedDataProtection' => true,
    //            'firstName' => $firstName,
    //            'lastName' => $lastName,
    //            'salutationId' => $salutationId,
    //            'street' => $address->getAddressLine1(),
    //            'zipcode' => $address->getPostalCode(),
    //            'countryId' => $countryId,
    //            'countryStateId' => $countryStateId,
    //            'phoneNumber' => $phone?->getNationalNumber(),
    //            'city' => $address->getAdminArea2(),
    //            'additionalAddressLine1' => $address->getAddressLine2(),

    public function __construct(
        public readonly array $data
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}
