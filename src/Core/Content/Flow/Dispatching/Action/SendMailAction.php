<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Content\Flow\Dispatching\DelayableAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopware\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopware\Core\Content\MailTemplate\Exception\SalesChannelNotFoundException;
use Shopware\Core\Content\MailTemplate\MailTemplateActions;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @package business-ops
 *
 * @deprecated tag:v6.5.0 - reason:remove-subscriber - FlowActions won't be executed over the event system anymore,
 * therefore the actions won't implement the EventSubscriberInterface anymore.
 */
#[Package('business-ops')]
class SendMailAction extends FlowAction implements DelayableAction
{
    public const ACTION_NAME = MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION;
    public const MAIL_CONFIG_EXTENSION = 'mail-attachments';
    private const RECIPIENT_CONFIG_ADMIN = 'admin';
    private const RECIPIENT_CONFIG_CUSTOM = 'custom';
    private const RECIPIENT_CONFIG_CONTACT_FORM_MAIL = 'contactFormMail';

    private EntityRepositoryInterface $mailTemplateRepository;

    private LoggerInterface $logger;

    private AbstractMailService $emailService;

    private EventDispatcherInterface $eventDispatcher;

    private EntityRepositoryInterface $mailTemplateTypeRepository;

    private Translator $translator;

    private Connection $connection;

    private LanguageLocaleCodeProvider $languageLocaleProvider;

    private bool $updateMailTemplate;

    /**
     * @internal
     */
    public function __construct(
        AbstractMailService $emailService,
        EntityRepositoryInterface $mailTemplateRepository,
        LoggerInterface $logger,
        EventDispatcherInterface $eventDispatcher,
        EntityRepositoryInterface $mailTemplateTypeRepository,
        Translator $translator,
        Connection $connection,
        LanguageLocaleCodeProvider $languageLocaleProvider,
        bool $updateMailTemplate
    ) {
        $this->mailTemplateRepository = $mailTemplateRepository;
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

    /**
     * @deprecated tag:v6.5.0 - reason:remove-subscriber - Will be removed
     */
    public static function getSubscribedEvents(): array
    {
        if (Feature::isActive('v6.5.0.0')) {
            return [];
        }

        return [
            self::getName() => 'handle',
        ];
    }

    /**
     * @return array<string>
     */
    public function requirements(): array
    {
        return [MailAware::class];
    }

    /**
     * @deprecated tag:v6.5.0 Will be removed, implement handleFlow instead
     *
     * @throws MailEventConfigurationException
     * @throws SalesChannelNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    public function handle(Event $event): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

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

        $injectedTranslator = $this->injectTranslator($mailEvent->getContext(), $mailEvent->getSalesChannelId());

        $data = new DataBag();

        $contactFormData = [];
        if ($mailEvent instanceof ContactFormEvent) {
            $contactFormData = $mailEvent->getContactFormData();
        }

        $recipients = $this->getRecipients($eventConfig['recipient'], $mailEvent->getMailStruct()->getRecipients(), $contactFormData);

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

        $data->set('attachmentsConfig', new MailAttachmentsConfig(
            $event->getContext(),
            $mailTemplate,
            $extension,
            $eventConfig,
            $mailEvent instanceof OrderAware ? $mailEvent->getOrderId() : null,
        ));

        $this->setReplyTo($data, $eventConfig, $contactFormData);

        $this->eventDispatcher->dispatch(new FlowSendMailActionEvent($data, $mailTemplate, $event));

        if ($data->has('templateId')) {
            $this->updateMailTemplateType(
                $event->getContext(),
                $event,
                $this->getTemplateData($mailEvent),
                $mailTemplate
            );
        }

        $templateData = array_merge([
            'eventName' => $mailEvent->getName(),
        ], $this->getTemplateData($mailEvent));

        $this->send($data, $event->getContext(), $templateData, $attachments, $extension, $injectedTranslator);
    }

    /**
     * @throws MailEventConfigurationException
     * @throws SalesChannelNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    public function handleFlow(StorableFlow $flow): void
    {
        $extension = $flow->getContext()->getExtension(self::MAIL_CONFIG_EXTENSION);
        if (!$extension instanceof MailSendSubscriberConfig) {
            $extension = new MailSendSubscriberConfig(false, [], []);
        }

        if ($extension->skip()) {
            return;
        }

        if (!$flow->hasStore(MailAware::MAIL_STRUCT) || !$flow->hasStore(MailAware::SALES_CHANNEL_ID)) {
            throw new MailEventConfigurationException('Not have data from MailAware', \get_class($flow));
        }

        $eventConfig = $flow->getConfig();
        if (empty($eventConfig['recipient'])) {
            throw new MailEventConfigurationException('The recipient value in the flow action configuration is missing.', \get_class($flow));
        }

        if (!isset($eventConfig['mailTemplateId'])) {
            return;
        }

        $mailTemplate = $this->getMailTemplate($eventConfig['mailTemplateId'], $flow->getContext());

        if ($mailTemplate === null) {
            return;
        }

        $injectedTranslator = $this->injectTranslator($flow->getContext(), $flow->getStore(MailAware::SALES_CHANNEL_ID));

        $data = new DataBag();

        $recipients = $this->getRecipients(
            $eventConfig['recipient'],
            $flow->getStore(MailAware::MAIL_STRUCT)['recipients'],
            $flow->getStore('contactFormData', []),
        );

        if (empty($recipients)) {
            return;
        }

        $data->set('recipients', $recipients);
        $data->set('senderName', $mailTemplate->getTranslation('senderName'));
        $data->set('salesChannelId', $flow->getStore(MailAware::SALES_CHANNEL_ID));

        $data->set('templateId', $mailTemplate->getId());
        $data->set('customFields', $mailTemplate->getCustomFields());
        $data->set('contentHtml', $mailTemplate->getTranslation('contentHtml'));
        $data->set('contentPlain', $mailTemplate->getTranslation('contentPlain'));
        $data->set('subject', $mailTemplate->getTranslation('subject'));
        $data->set('mediaIds', []);

        $data->set('attachmentsConfig', new MailAttachmentsConfig(
            $flow->getContext(),
            $mailTemplate,
            $extension,
            $eventConfig,
            $flow->getStore(OrderAware::ORDER_ID),
        ));

        $this->setReplyTo($data, $eventConfig, $flow->getStore('contactFormData', []));

        $this->eventDispatcher->dispatch(new FlowSendMailActionEvent($data, $mailTemplate, $flow));

        if ($data->has('templateId')) {
            $this->updateMailTemplateType(
                $flow->getContext(),
                $flow,
                $flow->data(),
                $mailTemplate
            );
        }

        $templateData = array_merge([
            'eventName' => $flow->getName(),
        ], $flow->data());

        $this->send($data, $flow->getContext(), $templateData, $attachments, $extension, $injectedTranslator);
    }

    /**
     * @param array<string, mixed> $templateData
     */
    private function send(DataBag $data, Context $context, array $templateData, MailSendSubscriberConfig $extension, bool $injectedTranslator): void
    {
        try {
            $this->emailService->send(
                $data->all(),
                $context,
                $templateData
            );
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

    /**
     * @param FlowEvent|StorableFlow $event
     * @param array<string, mixed> $templateData
     */
    private function updateMailTemplateType(
        Context $context,
        $event,
        array $templateData,
        MailTemplateEntity $mailTemplate
    ): void {
        if (!$mailTemplate->getMailTemplateTypeId()) {
            return;
        }

        if (!$this->updateMailTemplate) {
            return;
        }

        $mailTemplateTypeTranslation = $this->connection->fetchOne(
            'SELECT 1 FROM mail_template_type_translation WHERE language_id = :languageId AND mail_template_type_id =:mailTemplateTypeId',
            [
                'languageId' => Uuid::fromHexToBytes($context->getLanguageId()),
                'mailTemplateTypeId' => Uuid::fromHexToBytes($mailTemplate->getMailTemplateTypeId()),
            ]
        );

        if (!$mailTemplateTypeTranslation) {
            // Don't throw errors if this fails // Fix with NEXT-15475
            $this->logger->error(
                "Could not update mail template type, because translation for this language does not exits:\n"
                . 'Flow id: ' . $event->getFlowState()->flowId . "\n"
                . 'Sequence id: ' . $event->getFlowState()->getSequenceId()
            );

            return;
        }

        $this->mailTemplateTypeRepository->update([[
            'id' => $mailTemplate->getMailTemplateTypeId(),
            'templateData' => $templateData,
        ]], $context);
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
     *
     * @return array<string, mixed>
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

    private function injectTranslator(Context $context, ?string $salesChannelId): bool
    {
        if ($salesChannelId === null) {
            return false;
        }

        if ($this->translator->getSnippetSetId() !== null) {
            return false;
        }

        $this->translator->injectSettings(
            $salesChannelId,
            $context->getLanguageId(),
            $this->languageLocaleProvider->getLocaleForLanguageId($context->getLanguageId()),
            $context
        );

        return true;
    }

    /**
     * @param array<string, mixed> $recipients
     * @param array<string, mixed> $mailStructRecipients
     * @param array<int|string, mixed> $contactFormData
     *
     * @return array<int|string, string>
     */
    private function getRecipients(array $recipients, array $mailStructRecipients, array $contactFormData): array
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
                if (empty($contactFormData)) {
                    return [];
                }

                if (!\array_key_exists('email', $contactFormData)) {
                    return [];
                }

                return [$contactFormData['email'] => ($contactFormData['firstName'] ?? '') . ' ' . ($contactFormData['lastName'] ?? '')];
            default:
                return $mailStructRecipients;
        }
    }

    /**
     * @param array<string, mixed> $eventConfig
     * @param array<int|string, mixed> $contactFormData
     */
    private function setReplyTo(DataBag $data, array $eventConfig, array $contactFormData): void
    {
        if (empty($eventConfig['replyTo']) || !\is_string($eventConfig['replyTo'])) {
            return;
        }

        if ($eventConfig['replyTo'] !== self::RECIPIENT_CONFIG_CONTACT_FORM_MAIL) {
            $data->set('senderMail', $eventConfig['replyTo']);

            return;
        }

        if (empty($contactFormData['email']) || !\is_string($contactFormData['email'])) {
            return;
        }

        $data->set(
            'senderName',
            '{% if contactFormData.firstName is defined %}{{ contactFormData.firstName }}{% endif %} '
            . '{% if contactFormData.lastName is defined %}{{ contactFormData.lastName }}{% endif %}'
        );
        $data->set('senderMail', $contactFormData['email']);
    }
}
