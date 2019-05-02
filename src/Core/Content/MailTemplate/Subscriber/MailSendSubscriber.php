<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Subscriber;

use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Service\MailService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\BusinessEvent;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EventDataType;
use Shopware\Core\Framework\Event\MailActionInterface;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MailSendSubscriber implements EventSubscriberInterface
{
    public const ACTION_NAME = 'action.mail.send';

    /**
     * @var MailService
     */
    private $mailService;

    /**
     * @var EntityRepositoryInterface
     */
    private $mailTemplateRepository;

    public function __construct(MailService $mailService, EntityRepositoryInterface $mailTemplateRepository)
    {
        $this->mailService = $mailService;
        $this->mailTemplateRepository = $mailTemplateRepository;
    }

    public static function getSubscribedEvents()
    {
        return [
            self::ACTION_NAME => 'sendMail',
        ];
    }

    public function sendMail(BusinessEvent $event)
    {
        /** @var MailActionInterface|BusinessEventInterface $mailEvent */
        $mailEvent = $event->getEvent();

        if (!$mailEvent instanceof MailActionInterface) {
            throw new \Exception('todo better exception');
        }

        if (!array_key_exists('mail_template_id', $event->getConfig())) {
            throw new \Exception('todo better exception');
        }

        $mailTemplateId = $event->getConfig()['mail_template_id'];

        $criteria = new Criteria([$event->getConfig()['mail_template_id']]);
        /** @var MailTemplateEntity $mailTemplate */
        $mailTemplate = $this->mailTemplateRepository->search($criteria, $event->getContext())->get($mailTemplateId);

        $data = new DataBag();
        $data->set('recipients', $mailEvent->getMailStruct()->getRecipients());
        $data->set('senderMail', $mailTemplate->getSenderMail());
        $data->set('senderName', $mailTemplate->getSenderName());
        // todo
        $data->set('salesChannelId', Defaults::SALES_CHANNEL);
        $data->set('contentHtml', $mailTemplate->getContentHtml());
        $data->set('contentPlain', $mailTemplate->getContentPlain());
        $data->set('subject', $mailTemplate->getSubject());

        // todo add order to template data
        $this->mailService->send(
            $data,
            $event->getContext(),
            $this->getTemplateData($mailEvent));
    }

    /**
     * @param MailActionInterface|BusinessEventInterface $event
     */
    private function getTemplateData($event): array
    {
        $data = [];
        /* @var EventDataType $item */
        foreach (array_keys($event::getAvailableData()->toArray()) as $key) {
            $data[$key] = $event->$key;
        }

        return $data;
    }
}
