<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerChangedPaymentMethodEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerDeletedEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerGroupRegistrationAccepted;
use Shopware\Core\Checkout\Customer\Event\CustomerGroupRegistrationDeclined;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\Event\DoubleOptInGuestOrderEvent;
use Shopware\Core\Checkout\Customer\Event\GuestCustomerRegisterEvent;
use Shopware\Core\Checkout\Order\Event\OrderPaymentMethodChangedEvent;
use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterUnsubscribeEvent;
use Shopware\Core\Content\Product\SalesChannel\Review\Event\ReviewFormEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportLoggingEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\Recovery\UserRecoveryRequestEvent;

#[Package('services-settings')]
final class BusinessEvents
{
    public const CHECKOUT_CUSTOMER_BEFORE_LOGIN = CustomerBeforeLoginEvent::EVENT_NAME;

    public const CHECKOUT_CUSTOMER_LOGIN = CustomerLoginEvent::EVENT_NAME;

    public const CHECKOUT_CUSTOMER_LOGOUT = CustomerLogoutEvent::EVENT_NAME;

    public const CHECKOUT_CUSTOMER_DELETED = CustomerDeletedEvent::EVENT_NAME;

    public const USER_RECOVERY_REQUEST = UserRecoveryRequestEvent::EVENT_NAME;

    /**
     * @deprecated tag:v6.7.0 - will be removed, customer has no default payment method anymore
     */
    public const CHECKOUT_CUSTOMER_CHANGED_PAYMENT_METHOD = CustomerChangedPaymentMethodEvent::EVENT_NAME;

    public const CHECKOUT_ORDER_PLACED = CheckoutOrderPlacedEvent::EVENT_NAME;

    public const CHECKOUT_ORDER_PAYMENT_METHOD_CHANGED = OrderPaymentMethodChangedEvent::EVENT_NAME;

    public const CUSTOMER_ACCOUNT_RECOVER_REQUEST = CustomerAccountRecoverRequestEvent::EVENT_NAME;

    public const CUSTOMER_DOUBLE_OPT_IN_REGISTRATION = CustomerDoubleOptInRegistrationEvent::EVENT_NAME;

    public const CUSTOMER_GROUP_REGISTRATION_ACCEPTED = CustomerGroupRegistrationAccepted::EVENT_NAME;

    public const CUSTOMER_GROUP_REGISTRATION_DECLINED = CustomerGroupRegistrationDeclined::EVENT_NAME;

    public const CUSTOMER_REGISTER = CustomerRegisterEvent::EVENT_NAME;

    public const DOUBLE_OPT_IN_GUEST_ORDER = DoubleOptInGuestOrderEvent::EVENT_NAME;

    public const GUEST_CUSTOMER_REGISTER = GuestCustomerRegisterEvent::EVENT_NAME;

    public const CONTACT_FORM = ContactFormEvent::EVENT_NAME;

    public const REVIEW_FORM = ReviewFormEvent::EVENT_NAME;

    public const MAIL_BEFORE_SENT = MailBeforeSentEvent::EVENT_NAME;

    public const MAIL_BEFORE_VALIDATE = MailBeforeValidateEvent::EVENT_NAME;

    public const MAIL_SENT = MailSentEvent::EVENT_NAME;

    public const NEWSLETTER_CONFIRM = NewsletterConfirmEvent::EVENT_NAME;

    public const NEWSLETTER_REGISTER = NewsletterRegisterEvent::EVENT_NAME;

    public const NEWSLETTER_UNSUBSCRIBE = NewsletterUnsubscribeEvent::EVENT_NAME;

    public const PRODUCT_EXPORT_LOGGING = ProductExportLoggingEvent::NAME;

    private function __construct()
    {
    }
}
