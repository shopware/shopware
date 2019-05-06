<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Subscriber;

use Shopware\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Service\MailService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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

    public function __construct(
        MailService $mailService,
        EntityRepositoryInterface $mailTemplateRepository
    ) {
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
            throw new MailEventConfigurationException('Not a instance of MailActionInterface', get_class($mailEvent));
        }

        if (!array_key_exists('mail_template_type_id', $event->getConfig())) {
            throw new MailEventConfigurationException('Configuration mail_template_type_id missing.', get_class($mailEvent));
        }

        $mailTemplateTypeId = $event->getConfig()['mail_template_type_id'];

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mailTemplateTypeId', $mailTemplateTypeId));
        $criteria->addFilter(new EqualsFilter('mail_template.salesChannels.id', $mailEvent->getSalesChannelId()));
        $criteria->setLimit(1);
        /** @var MailTemplateEntity|null $mailTemplate */
        $mailTemplate = $this->mailTemplateRepository->search($criteria, $event->getContext())->first();

        if ($mailTemplate === null) {
            return;
        }

        $data = new DataBag();
        $data->set('recipients', $mailEvent->getMailStruct()->getRecipients());
        $data->set('senderName', $mailTemplate->getSenderName());
        $data->set('salesChannelId', $mailEvent->getSalesChannelId());

        $data->set('contentHtml', $mailTemplate->getContentHtml());
        $data->set('contentPlain', $mailTemplate->getContentPlain());
        $data->set('subject', $mailTemplate->getSubject());
        $data->set('mediaIds', []);

        $this->mailService->send(
            $data->all(),
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
