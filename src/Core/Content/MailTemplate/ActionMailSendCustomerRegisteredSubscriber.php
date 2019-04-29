<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate;

use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Content\MailTemplate\Service\MailFinder;
use Shopware\Core\Content\MailTemplate\Service\MailService;
use Shopware\Core\Framework\Event\BusinessEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ActionMailSendCustomerRegisteredSubscriber implements EventSubscriberInterface
{
    /**
     * @var MailFinder
     */
    private $mailFinder;

    /**
     * @var MailService
     */
    private $mailService;

    public function __construct(MailFinder $mailFinder, MailService $mailService)
    {
        $this->mailFinder = $mailFinder;
        $this->mailService = $mailService;
    }

    public static function getSubscribedEvents()
    {
        return [
            'action.mail.send.customer.registered' => 'sendCustomerRegistered',
        ];
    }

    public function sendCustomerRegistered(BusinessEvent $event): void
    {
        /** @var CustomerRegisterEvent $businessEvent */
        $businessEvent = $event->getEvent();
        $customer = $businessEvent->getCustomer();
        $mailTemplate = $this->mailFinder->getMail(
            $event->getContext(), $customer['salesChannelId'], $event->getActionName()
        );

        $sendMailDataBag = new DataBag();

        $sendMailDataBag->add(
            [
                'recipient' => $customer['email'],
                'salesChannelId' => $customer['salesChannelId'],
                'contentHtml' => $mailTemplate->getContentHtml(),
                'contentPlain' => $mailTemplate->getContentPlain(),
                'subject' => 'Your Registration xyz',
                'senderMail' => 'getsender@shopware.com',
                'senderName' => 'saleschannel get sender Name',
            ]
        );

        $this->mailService->send($sendMailDataBag, $event->getContext(), $customer);
    }
}
