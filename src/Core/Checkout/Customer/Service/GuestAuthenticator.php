<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Service;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

#[Package('checkout')]
class GuestAuthenticator
{
    public function validate(OrderEntity $order, Request $request): void
    {
        $isOrderByGuest = $order->getOrderCustomer()?->getCustomer()?->getGuest();

        if (!$isOrderByGuest) {
            throw CustomerException::customerNotLoggedIn();
        }

        $email = RequestParamHelper::get($request, 'email');
        $zipcode = RequestParamHelper::get($request, 'zipcode');
        if (!$email || !$zipcode) {
            throw CustomerException::guestNotAuthenticated();
        }

        // Verify email and zip code with this order
        $billingAddress = $order->getBillingAddress();
        if ($billingAddress === null
            || mb_strtolower($email) !== mb_strtolower($order->getOrderCustomer()?->getEmail() ?: '')
            || mb_strtoupper($zipcode) !== mb_strtoupper($billingAddress->getZipcode() ?: '')) {
            throw CustomerException::wrongGuestCredentials();
        }
    }
}
