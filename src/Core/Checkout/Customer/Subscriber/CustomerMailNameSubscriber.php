<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @description Mail templates render the customer name by concatenating the first and last name.
 * A company account may have neither, so the company name is written onto a copy of the customer
 * that is handed to the template. Templates already stored in a shop are therefore covered without
 * being rewritten. Orders do not need this, because the order customer snapshot already carries the
 * company name.
 */
#[Package('checkout')]
class CustomerMailNameSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            MailBeforeValidateEvent::class => 'onMailBeforeValidate',
        ];
    }

    public function onMailBeforeValidate(MailBeforeValidateEvent $event): void
    {
        $templateData = $event->getTemplateData();
        $customer = $templateData['customer'] ?? null;

        if (!$customer instanceof CustomerEntity) {
            return;
        }

        $company = trim($customer->getCompany() ?? '');

        if ($company === '' || $customer->getAccountType() !== CustomerEntity::ACCOUNT_TYPE_BUSINESS) {
            return;
        }

        if (trim($customer->getFirstName() . $customer->getLastName()) !== '') {
            return;
        }

        // the entity is shared with whatever dispatched the mail, so the rendering copy is separate
        $renderCustomer = clone $customer;
        $renderCustomer->setLastName($company);

        $templateData['customer'] = $renderCustomer;
        $event->setTemplateData($templateData);
    }
}
