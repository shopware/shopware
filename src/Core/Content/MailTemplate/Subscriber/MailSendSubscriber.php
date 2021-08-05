<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\MailTemplate\Event\MailSendSubscriberBridgeEvent;
use Shopware\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopware\Core\Content\MailTemplate\Exception\SalesChannelNotFoundException;
use Shopware\Core\Content\MailTemplate\MailTemplateActions;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\BusinessEvent;
use Shopware\Core\Framework\Event\MailActionInterface;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class MailSendSubscriber implements EventSubscriberInterface
{
    public const ACTION_NAME = MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION;
    public const MAIL_CONFIG_EXTENSION = 'mail-attachments';

    private EntityRepositoryInterface $mailTemplateRepository;

    private MediaService $mediaService;

    private EntityRepositoryInterface $mediaRepository;

    private DocumentService $documentService;

    private EntityRepositoryInterface $documentRepository;

    private LoggerInterface $logger;

    private AbstractMailService $emailService;

    private EventDispatcherInterface $eventDispatcher;

    private EntityRepositoryInterface $mailTemplateTypeRepository;

    private Translator $translator;

    private EntityRepositoryInterface $languageRepository;

    public function __construct(
        AbstractMailService $emailService,
        EntityRepositoryInterface $mailTemplateRepository,
        MediaService $mediaService,
        EntityRepositoryInterface $mediaRepository,
        EntityRepositoryInterface $documentRepository,
        DocumentService $documentService,
        LoggerInterface $logger,
        EventDispatcherInterface $eventDispatcher,
        EntityRepositoryInterface $mailTemplateTypeRepository,
        Translator $translator,
        EntityRepositoryInterface $languageRepository
    ) {
        $this->mailTemplateRepository = $mailTemplateRepository;
        $this->mediaService = $mediaService;
        $this->mediaRepository = $mediaRepository;
        $this->documentRepository = $documentRepository;
        $this->documentService = $documentService;
        $this->logger = $logger;
        $this->emailService = $emailService;
        $this->eventDispatcher = $eventDispatcher;
        $this->mailTemplateTypeRepository = $mailTemplateTypeRepository;
        $this->translator = $translator;
        $this->languageRepository = $languageRepository;
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

        $extension = $event->getContext()->getExtension(self::MAIL_CONFIG_EXTENSION);
        if (!$extension instanceof MailSendSubscriberConfig) {
            $extension = new MailSendSubscriberConfig(false, [], []);
        }

        if ($extension->skip()) {
            return;
        }

        if (!$mailEvent instanceof MailActionInterface) {
            throw new MailEventConfigurationException('Not a instance of MailActionInterface', \get_class($mailEvent));
        }

        $config = $event->getConfig();

        if (!isset($config['mail_template_id'])) {
            return;
        }

        $mailTemplate = $this->getMailTemplate($config['mail_template_id'], $event->getContext());

        if ($mailTemplate === null) {
            return;
        }

        $injectedTranslator = $this->injectTranslator($mailEvent);

        $data = new DataBag();

        $recipients = $mailEvent->getMailStruct()->getRecipients();
        if (isset($config['recipients']) && !empty($config['recipients'])) {
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

        $attachments = $this->buildAttachments($event, $mailTemplate, $extension);

        if (!empty($attachments)) {
            $data->set('binAttachments', $attachments);
        }

        $this->eventDispatcher->dispatch(new MailSendSubscriberBridgeEvent($data, $mailTemplate, $event));

        if ($data->has('templateId')) {
            try {
                $this->mailTemplateTypeRepository->update([[
                    'id' => $mailTemplate->getMailTemplateTypeId(),
                    'templateData' => $this->getTemplateData($mailEvent),
                ]], $mailEvent->getContext());
            } catch (\Throwable $e) {
                // Dont throw errors if this fails // Fix with NEXT-15475
            }
        }

        try {
            $this->emailService->send(
                $data->all(),
                $event->getContext(),
                $this->getTemplateData($mailEvent)
            );

            $writes = array_map(static function ($id) {
                return ['id' => $id, 'sent' => true];
            }, $extension->getDocumentIds());

            if (!empty($writes)) {
                $this->documentRepository->update($writes, $event->getContext());
            }
        } catch (\Exception $e) {
            $this->logger->error(
                "Could not send mail:\n"
                . $e->getMessage() . "\n"
                . 'Error Code:' . $e->getCode() . "\n"
                . "Template data: \n"
                . json_encode($data->all()) . "\n"
            );
        }

        if ($injectedTranslator) {
            $this->translator->resetInjection();
        }
    }

    private function getMailTemplate(string $id, Context $context): ?MailTemplateEntity
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

        foreach (array_keys($event::getAvailableData()->toArray()) as $key) {
            $getter = 'get' . ucfirst($key);
            if (method_exists($event, $getter)) {
                $data[$key] = $event->$getter();
            } else {
                throw new MailEventConfigurationException('Data for ' . $key . ' not available.', \get_class($event));
            }
        }

        return $data;
    }

    private function buildAttachments(BusinessEvent $event, MailTemplateEntity $mailTemplate, MailSendSubscriberConfig $config): array
    {
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

        if (!empty($config->getMediaIds())) {
            $entities = $this->mediaRepository->search(new Criteria($config->getMediaIds()), $event->getContext());

            foreach ($entities as $media) {
                $attachments[] = $this->mediaService->getAttachment($media, $event->getContext());
            }
        }

        if (!empty($config->getDocumentIds())) {
            $criteria = new Criteria($config->getDocumentIds());
            $criteria->addAssociation('documentMediaFile');
            $criteria->addAssociation('documentType');

            $entities = $this->documentRepository->search($criteria, $event->getContext());

            foreach ($entities as $document) {
                $document = $this->documentService->getDocument($document, $event->getContext());

                $attachments[] = [
                    'content' => $document->getFileBlob(),
                    'fileName' => $document->getFilename(),
                    'mimeType' => $document->getContentType(),
                ];
            }
        }

        return $attachments;
    }

    private function injectTranslator(MailActionInterface $event): bool
    {
        if ($event->getSalesChannelId() === null) {
            return false;
        }

        if ($this->translator->getSnippetSetId() !== null) {
            return false;
        }

        $criteria = new Criteria([$event->getContext()->getLanguageId()]);
        $criteria->addAssociation('locale');

        /** @var LanguageEntity $language */
        $language = $this->languageRepository->search($criteria, $event->getContext())->first();
        $locale = $language->getLocale();
        \assert($locale instanceof LocaleEntity);

        $this->translator->injectSettings(
            $event->getSalesChannelId(),
            $event->getContext()->getLanguageId(),
            $locale->getCode(),
            $event->getContext()
        );

        return true;
    }
}
