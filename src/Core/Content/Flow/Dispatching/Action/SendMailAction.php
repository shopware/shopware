<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopware\Core\Content\MailTemplate\Exception\SalesChannelNotFoundException;
use Shopware\Core\Content\MailTemplate\MailTemplateActions;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SendMailAction extends FlowAction
{
    public const ACTION_NAME = MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION;
    public const MAIL_CONFIG_EXTENSION = 'mail-attachments';
    private const RECIPIENT_CONFIG_ADMIN = 'admin';
    private const RECIPIENT_CONFIG_CUSTOM = 'custom';
    private const RECIPIENT_CONFIG_CONTACT_FORM_MAIL = 'contactFormMail';

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

    private Connection $connection;

    private LanguageLocaleCodeProvider $languageLocaleProvider;

    private bool $updateMailTemplate;

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
        Connection $connection,
        LanguageLocaleCodeProvider $languageLocaleProvider,
        bool $updateMailTemplate
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
        $this->connection = $connection;
        $this->languageLocaleProvider = $languageLocaleProvider;
        $this->updateMailTemplate = $updateMailTemplate;
    }

    public static function getName(): string
    {
        return 'action.mail.send';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            self::getName() => 'handle',
        ];
    }

    public function requirements(): array
    {
        return [MailAware::class];
    }

    /**
     * @throws MailEventConfigurationException
     * @throws SalesChannelNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    public function handle(Event $event): void
    {
        if (!$event instanceof FlowEvent) {
            return;
        }

        $mailEvent = $event->getEvent();

        $extension = $event->getContext()->getExtension(self::MAIL_CONFIG_EXTENSION);
        if (!$extension instanceof MailSendSubscriberConfig) {
            $extension = new MailSendSubscriberConfig(false, [], []);
        }

        if ($extension->skip()) {
            return;
        }

        if (!$mailEvent instanceof MailAware) {
            throw new MailEventConfigurationException('Not an instance of MailAware', \get_class($mailEvent));
        }

        $eventConfig = $event->getConfig();

        if (empty($eventConfig['recipient'])) {
            throw new MailEventConfigurationException('The recipient value in the flow action configuration is missing.', \get_class($mailEvent));
        }

        if (!isset($eventConfig['mailTemplateId'])) {
            return;
        }

        $mailTemplate = $this->getMailTemplate($eventConfig['mailTemplateId'], $event->getContext());

        if ($mailTemplate === null) {
            return;
        }

        $injectedTranslator = $this->injectTranslator($mailEvent);

        $data = new DataBag();

        $recipients = $this->getRecipients($eventConfig['recipient'], $mailEvent);

        if (empty($recipients)) {
            return;
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

        $attachments = array_unique($this->buildAttachments($mailEvent, $mailTemplate, $extension, $eventConfig), \SORT_REGULAR);

        if (!empty($attachments)) {
            $data->set('binAttachments', $attachments);
        }

        $this->eventDispatcher->dispatch(new FlowSendMailActionEvent($data, $mailTemplate, $event));

        if ($data->has('templateId')) {
            $this->updateMailTemplateType($event, $mailEvent, $mailTemplate);
        }

        try {
            $this->emailService->send(
                $data->all(),
                $event->getContext(),
                $this->getTemplateData($mailEvent)
            );

            $writes = array_map(static function ($id) {
                return ['id' => $id, 'sent' => true];
            }, array_column($attachments, 'id'));

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

    private function updateMailTemplateType(FlowEvent $event, MailAware $mailAware, MailTemplateEntity $mailTemplate): void
    {
        if (!$mailTemplate->getMailTemplateTypeId()) {
            return;
        }

        if (!$this->updateMailTemplate) {
            return;
        }

        $mailTemplateTypeTranslation = $this->connection->fetchOne(
            'SELECT 1 FROM mail_template_type_translation WHERE language_id = :languageId AND mail_template_type_id =:mailTemplateTypeId',
            [
                'languageId' => Uuid::fromHexToBytes($event->getContext()->getLanguageId()),
                'mailTemplateTypeId' => Uuid::fromHexToBytes($mailTemplate->getMailTemplateTypeId()),
            ]
        );

        if (!$mailTemplateTypeTranslation) {
            // Don't throw errors if this fails // Fix with NEXT-15475
            $this->logger->error(
                "Could not update mail template type, because translation for this language does not exits:\n"
                . 'Flow id: ' . $event->getFlowState()->flowId . "\n"
                . 'Sequence id: ' . $event->getFlowState()->sequenceId
            );

            return;
        }

        $this->mailTemplateTypeRepository->update([[
            'id' => $mailTemplate->getMailTemplateTypeId(),
            'templateData' => $this->getTemplateData($mailAware),
        ]], $mailAware->getContext());
    }

    private function getMailTemplate(string $id, Context $context): ?MailTemplateEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->setTitle('send-mail::load-mail-template');
        $criteria->addAssociation('media.media');
        $criteria->setLimit(1);

        return $this->mailTemplateRepository
            ->search($criteria, $context)
            ->first();
    }

    /**
     * @throws MailEventConfigurationException
     */
    private function getTemplateData(MailAware $event): array
    {
        $data = [];

        foreach (array_keys($event::getAvailableData()->toArray()) as $key) {
            $getter = 'get' . ucfirst($key);
            if (!method_exists($event, $getter)) {
                throw new MailEventConfigurationException('Data for ' . $key . ' not available.', \get_class($event));
            }
            $data[$key] = $event->$getter();
        }

        return $data;
    }

    private function buildAttachments(MailAware $mailEvent, MailTemplateEntity $mailTemplate, MailSendSubscriberConfig $extensions, array $eventConfig): array
    {
        $attachments = [];

        if ($mailTemplate->getMedia() !== null) {
            foreach ($mailTemplate->getMedia() as $mailTemplateMedia) {
                if ($mailTemplateMedia->getMedia() === null) {
                    continue;
                }
                if ($mailTemplateMedia->getLanguageId() !== null && $mailTemplateMedia->getLanguageId() !== $mailEvent->getContext()->getLanguageId()) {
                    continue;
                }

                $attachments[] = $this->mediaService->getAttachment(
                    $mailTemplateMedia->getMedia(),
                    $mailEvent->getContext()
                );
            }
        }

        if (!empty($extensions->getMediaIds())) {
            $criteria = new Criteria($extensions->getMediaIds());
            $criteria->setTitle('send-mail::load-media');

            $entities = $this->mediaRepository->search($criteria, $mailEvent->getContext());

            foreach ($entities as $media) {
                $attachments[] = $this->mediaService->getAttachment($media, $mailEvent->getContext());
            }
        }

        if (!empty($extensions->getDocumentIds())) {
            $attachments = $this->buildOrderAttachments($extensions->getDocumentIds(), $attachments, $mailEvent->getContext());
        }

        if (empty($eventConfig['documentTypeIds']) || !\is_array($eventConfig['documentTypeIds']) || !$mailEvent instanceof OrderAware) {
            return $attachments;
        }

        return $this->buildFlowSettingAttachments($mailEvent->getOrderId(), $eventConfig['documentTypeIds'], $attachments, $mailEvent->getContext());
    }

    private function injectTranslator(MailAware $event): bool
    {
        if ($event->getSalesChannelId() === null) {
            return false;
        }

        if ($this->translator->getSnippetSetId() !== null) {
            return false;
        }

        $this->translator->injectSettings(
            $event->getSalesChannelId(),
            $event->getContext()->getLanguageId(),
            $this->languageLocaleProvider->getLocaleForLanguageId($event->getContext()->getLanguageId()),
            $event->getContext()
        );

        return true;
    }

    private function getRecipients(array $recipients, MailAware $mailEvent): array
    {
        switch ($recipients['type']) {
            case self::RECIPIENT_CONFIG_CUSTOM:
                return $recipients['data'];
            case self::RECIPIENT_CONFIG_ADMIN:
                $admins = $this->connection->fetchAllAssociative(
                    'SELECT first_name, last_name, email FROM user WHERE admin = true'
                );
                $emails = [];
                foreach ($admins as $admin) {
                    $emails[$admin['email']] = $admin['first_name'] . ' ' . $admin['last_name'];
                }

                return $emails;
            case self::RECIPIENT_CONFIG_CONTACT_FORM_MAIL:
                if (!$mailEvent instanceof ContactFormEvent) {
                    return [];
                }
                $data = $mailEvent->getContactFormData();

                if (!\array_key_exists('email', $data)) {
                    return [];
                }

                return [$data['email'] => ($data['firstName'] ?? '') . ' ' . ($data['lastName'] ?? '')];
            default:
                return $mailEvent->getMailStruct()->getRecipients();
        }
    }

    private function buildOrderAttachments(array $documentIds, array $attachments, Context $context): array
    {
        $criteria = new Criteria($documentIds);
        $criteria->setTitle('send-mail::load-attachments');
        $criteria->addAssociation('documentMediaFile');
        $criteria->addAssociation('documentType');

        $entities = $this->documentRepository->search($criteria, $context);

        return $this->mappingAttachmentsInfo($entities, $attachments, $context);
    }

    private function buildFlowSettingAttachments(string $orderId, array $documentTypeIds, array $attachments, Context $context): array
    {
        $documents = $this->connection->fetchAllAssociative(
            'SELECT
                LOWER(hex(`document`.`document_type_id`)) as doc_type,
                LOWER(hex(`document`.`id`)) as doc_id,
                `document`.`created_at` as newest_date
            FROM
                `document`
            WHERE
                HEX(`document`.`order_id`) = :orderId
                AND HEX(`document`.`document_type_id`) IN (:documentTypeIds)
            ORDER BY `document`.`created_at` DESC',
            [
                'orderId' => $orderId,
                'documentTypeIds' => $documentTypeIds,
            ],
            [
                'documentTypeIds' => Connection::PARAM_STR_ARRAY,
            ]
        );

        $documentsGroupByType = FetchModeHelper::group($documents);

        foreach ($documentsGroupByType as $document) {
            $documentIds[] = array_shift($document)['doc_id'];
        }

        if (empty($documentIds)) {
            return $attachments;
        }

        $criteria = new Criteria($documentIds);
        $criteria->setTitle('send-mail::load-flow-documents');
        $criteria->addAssociations(['documentMediaFile', 'documentType']);

        $entities = $this->documentRepository->search($criteria, $context);

        return $this->mappingAttachmentsInfo($entities, $attachments, $context);
    }

    private function mappingAttachmentsInfo(EntityCollection $entities, array $attachments, Context $context): array
    {
        foreach ($entities as $document) {
            $documentId = $document->getId();
            $document = $this->documentService->getDocument($document, $context);

            $attachments[] = [
                'id' => $documentId,
                'content' => $document->getFileBlob(),
                'fileName' => $document->getFilename(),
                'mimeType' => $document->getContentType(),
            ];
        }

        return $attachments;
    }
}
