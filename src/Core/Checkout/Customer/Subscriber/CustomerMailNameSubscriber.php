<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
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
        $changed = false;

        $renderCustomers = [];

        $customer = $this->renderCustomer($templateData['customer'] ?? null);
        if ($customer !== null) {
            $templateData['customer'] = $customer;
            $renderCustomers[] = $customer;
            $changed = true;
        }

        $recovery = $templateData['customerRecovery'] ?? null;
        if ($recovery instanceof CustomerRecoveryEntity) {
            $recoveryCustomer = $this->renderCustomer($recovery->getCustomer());

            if ($recoveryCustomer !== null) {
                $renderRecovery = clone $recovery;
                $renderRecovery->setCustomer($recoveryCustomer);
                $templateData['customerRecovery'] = $renderRecovery;
                $renderCustomers[] = $recoveryCustomer;
                $changed = true;
            }
        }

        if ($changed) {
            $event->setTemplateData($templateData);
            $this->renameRecipients($event, $renderCustomers);
        }
    }

    /**
     * @param list<CustomerEntity> $renderCustomers
     */
    private function renameRecipients(MailBeforeValidateEvent $event, array $renderCustomers): void
    {
        $data = $event->getData();
        $recipients = $data['recipients'] ?? [];
        $changed = false;

        foreach ($renderCustomers as $renderCustomer) {
            $email = $renderCustomer->getEmail();

            if (!\array_key_exists($email, $recipients) || trim((string) $recipients[$email]) !== '') {
                continue;
            }

            $recipients[$email] = $renderCustomer->getLastName();
            $changed = true;
        }

        if ($changed) {
            $data['recipients'] = $recipients;
            $event->setData($data);
        }
    }

    private function renderCustomer(mixed $customer): ?CustomerEntity
    {
        if (!$customer instanceof CustomerEntity) {
            return null;
        }

        $company = trim($customer->getCompany() ?? '');

        if ($company === '' || !$customer->isBusinessAccount()) {
            return null;
        }

        if (trim($customer->getFirstName() . $customer->getLastName()) !== '') {
            return null;
        }

        $renderCustomer = clone $customer;
        $renderCustomer->setLastName($company);

        return $renderCustomer;
    }
}
