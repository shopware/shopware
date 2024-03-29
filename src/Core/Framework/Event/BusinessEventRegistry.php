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
class BusinessEventRegistry
{
    /**
     * @var list<class-string>
     */
    private array $classes = [
        CustomerBeforeLoginEvent::class,
        CustomerLoginEvent::class,
        CustomerLogoutEvent::class,
        CustomerDeletedEvent::class,
        UserRecoveryRequestEvent::class,
        CustomerChangedPaymentMethodEvent::class,
        CheckoutOrderPlacedEvent::class,
        OrderPaymentMethodChangedEvent::class,
        CustomerAccountRecoverRequestEvent::class,
        CustomerDoubleOptInRegistrationEvent::class,
        CustomerGroupRegistrationAccepted::class,
        CustomerGroupRegistrationDeclined::class,
        CustomerRegisterEvent::class,
        DoubleOptInGuestOrderEvent::class,
        GuestCustomerRegisterEvent::class,
        ContactFormEvent::class,
        ReviewFormEvent::class,
        MailBeforeSentEvent::class,
        MailBeforeValidateEvent::class,
        MailSentEvent::class,
        NewsletterConfirmEvent::class,
        NewsletterRegisterEvent::class,
        NewsletterUnsubscribeEvent::class,
        ProductExportLoggingEvent::class,
    ];

    /**
     * @param list<class-string> $classes
     */
    public function addClasses(array $classes): void
    {
        /** @var list<class-string> */
        $classes = array_unique(array_merge($this->classes, $classes));
        $this->classes = $classes;
    }

    /**
     * @return list<class-string>
     */
    public function getClasses(): array
    {
        return $this->classes;
    }
}
