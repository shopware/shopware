<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
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

        $renderCustomer = clone $customer;
        $renderCustomer->setLastName($company);

        $templateData['customer'] = $renderCustomer;
        $event->setTemplateData($templateData);
    }
}
