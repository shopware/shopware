<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Subscriber;

use Shopware\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopware\Core\Content\MailTemplate\Exception\SalesChannelNotFoundException;
use Shopware\Core\Content\MailTemplate\MailTemplateActions;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Service\MailServiceInterface;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\BusinessEvent;
use Shopware\Core\Framework\Event\EventData\EventDataType;
use Shopware\Core\Framework\Event\MailActionInterface;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MailSendSubscriber implements EventSubscriberInterface
{
    public const ACTION_NAME = MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION;
    public const SKIP_MAILS = 'skip-mails';

    /**
     * @var MailServiceInterface
     */
    private $mailService;

    /**
     * @var EntityRepositoryInterface
     */
    private $mailTemplateRepository;

    /**
     * @var MediaService
     */
    private $mediaService;

    public function __construct(
        MailServiceInterface $mailService,
        EntityRepositoryInterface $mailTemplateRepository,
        MediaService $mediaService
    ) {
        $this->mailService = $mailService;
        $this->mailTemplateRepository = $mailTemplateRepository;
        $this->mediaService = $mediaService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            self::ACTION_NAME => 'sendMail',
        ];
    }

    /**
     * @throws MailEventConfigurationException
     * @throws SalesChannelNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    public function sendMail(BusinessEvent $event): void
    {
        $mailEvent = $event->getEvent();

        if ($event->getContext()->hasExtension(self::SKIP_MAILS)) {
            return;
        }

        if (!$mailEvent instanceof MailActionInterface) {
            throw new MailEventConfigurationException('Not a instance of MailActionInterface', get_class($mailEvent));
        }

        $config = $event->getConfig();

        $mailTemplate = null;
        if (isset($config['mail_template_type_id'])) {
            $mailTemplate = $this->getMailTemplateByType($config['mail_template_type_id'], $event->getContext(), $mailEvent->getSalesChannelId());
        } elseif (isset($config['mail_template_id'])) {
            $mailTemplate = $this->getMailTemplate($config['mail_template_id'], $event->getContext());
        }

        if ($mailTemplate === null) {
            return;
        }

        $data = new DataBag();

        $recipients = $mailEvent->getMailStruct()->getRecipients();
        if (isset($config['recipients'])) {
            $recipients = $config['recipients'];
        }

        $data->set('recipients', $recipients);
        $data->set('senderName', $mailTemplate->getTranslation('senderName'));
        $data->set('salesChannelId', $mailEvent->getSalesChannelId());

        $data->set('templateId', $mailTemplate->getId());
        $data->set('customFields', $mailTemplate->getCustomFields());
        $data->set('contentHtml', $mailTemplate->getTranslation('contentHtml'));
        $data->set('contentPlain', $mailTemplate->getTranslation('contentPlain'));
        $data->set('subject', $mailTemplate->getTranslation('subject'));
        $data->set('mediaIds', []);

        $attachments = [];
        if ($mailTemplate->getMedia() !== null) {
            foreach ($mailTemplate->getMedia() as $mailTemplateMedia) {
                if ($mailTemplateMedia->getMedia() === null) {
                    continue;
                }
                if ($mailTemplateMedia->getLanguageId() !== null && $mailTemplateMedia->getLanguageId() !== $event->getContext()->getLanguageId()) {
                    continue;
                }

                $attachments[] = $this->mediaService->getAttachment(
                    $mailTemplateMedia->getMedia(),
                    $event->getContext()
                );
            }
        }
        if (!empty($attachments)) {
            $data->set('binAttachments', $attachments);
        }

        $this->mailService->send(
            $data->all(),
            $event->getContext(),
            $this->getTemplateData($mailEvent)
        );
    }

    private function getMailTemplateByType($typeId, Context $context, ?string $salesChannelId)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mailTemplateTypeId', $typeId));
        $criteria->addAssociation('media.media');
        $criteria->setLimit(1);

        if ($salesChannelId === null) {
            return $this->mailTemplateRepository
                ->search($criteria, $context)
                ->first();
        }

        $criteria->addFilter(new EqualsFilter('mail_template.salesChannels.salesChannel.id', $salesChannelId));

        /** @var MailTemplateEntity|null $mailTemplate */
        $mailTemplate = $this->mailTemplateRepository
            ->search($criteria, $context)
            ->first();

        // Fallback if no template for the saleschannel is found
        if ($mailTemplate !== null) {
            return $mailTemplate;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mailTemplateTypeId', $typeId));
        $criteria->addAssociation('media.media');
        $criteria->setLimit(1);

        /* @var MailTemplateEntity|null $mailTemplate */
        return $this->mailTemplateRepository
            ->search($criteria, $context)
            ->first();
    }

    private function getMailTemplate(string $id, Context $context)
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('media.media');
        $criteria->setLimit(1);

        return $this->mailTemplateRepository
            ->search($criteria, $context)
            ->first();
    }

    /**
     * @throws MailEventConfigurationException
     */
    private function getTemplateData(MailActionInterface $event): array
    {
        $data = [];
        /* @var EventDataType $item */
        foreach (array_keys($event::getAvailableData()->toArray()) as $key) {
            $getter = 'get' . ucfirst($key);
            if (method_exists($event, $getter)) {
                $data[$key] = $event->$getter();
            } else {
                throw new MailEventConfigurationException('Data for ' . $key . ' not available.', get_class($event));
            }
        }

        return $data;
    }
}
