<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate;

use Shopware\Core\Checkout\Cart\Order\Event\OrderDoneEvent;
use Shopware\Core\Content\MailTemplate\Service\MailFinder;
use Shopware\Core\Content\MailTemplate\Service\MailService;
use Shopware\Core\Framework\Event\BusinessEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ActionMailSendOrderDoneSubscriber implements EventSubscriberInterface
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
            'action.mail.send.order.done' => 'sendOrderDone',
        ];
    }

    public function sendOrderDone(BusinessEvent $event): void
    {
        /** @var OrderDoneEvent $businessEvent */
        $businessEvent = $event->getEvent();
        $order = $businessEvent->getOrder();
        $mailTemplate = $this->mailFinder->getMail(
            $event->getContext(), $order['salesChannelId'], $event->getActionName()
        );

        $sendMailDataBag = new DataBag();

        $sendMailDataBag->add(
            [
                'recipient' => $order['orderCustomer']['email'],
                'salesChannelId' => $order['salesChannelId'],
                'contentHtml' => $mailTemplate->getContentHtml(),
                'contentPlain' => $mailTemplate->getContentPlain(),
                'subject' => 'Your Order xyz',
                'senderMail' => 'getsender@shopware.com',
                'senderName' => 'saleschannel get sender Name',
            ]
        );

        $this->mailService->send($sendMailDataBag, $event->getContext(), $order);
    }
}
