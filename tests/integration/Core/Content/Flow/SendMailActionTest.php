<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Flow;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Content\Flow\Dispatching\FlowFactory;
use Shopware\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Shopware\Core\Content\Mail\Service\MailAttachmentsBuilder;
use Shopware\Core\Content\Mail\Service\MailerTransportDecorator;
use Shopware\Core\Content\Mail\Service\MailFactory;
use Shopware\Core\Content\Mail\Service\MailService;
use Shopware\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * @internal
 */
#[Package('services-settings')]
class SendMailActionTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    private Connection $connection;

    /**
     * @var EntityRepository<DocumentCollection>
     */
    private EntityRepository $documentRepository;

    /**
     * @var EntityRepository<MailTemplateCollection>
     */
    private EntityRepository $mailTemplateRepository;

    protected function setUp(): void
    {
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->documentRepository = $this->getContainer()->get('document.repository');
        $this->mailTemplateRepository = $this->getContainer()->get('mail_template.repository');
    }

    /**
     * @param array<string>|null $documentTypeIds
     * @param array<string, mixed> $recipients
     */
    #[DataProvider('sendMailProvider')]
    public function testEmailSend(array $recipients, ?array $documentTypeIds = [], ?bool $hasOrderSettingAttachment = true): void
    {
        $documentRepository = $this->getContainer()->get('document.repository');
        $orderRepository = $this->getContainer()->get('order.repository');

        $criteria = new Criteria();
        $criteria->setLimit(1);

        $context = Context::createDefaultContext();

        $customerId = $this->createCustomer($context);
        $orderId = $this->createOrder($customerId, $context);

        $mailTemplateId = $this->retrieveMailTemplateId();

        $config = array_filter([
            'mailTemplateId' => $mailTemplateId,
            'recipient' => $recipients,
            'documentTypeIds' => $documentTypeIds,
        ]);

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions.stateMachineState');
        /** @var OrderEntity $order */
        $order = $orderRepository->search($criteria, $context)->first();
        $event = new CheckoutOrderPlacedEvent($context, $order, TestDefaults::SALES_CHANNEL);

        $documentIdOlder = null;
        $documentIdNewer = null;
        $documentIds = [];

        if ($documentTypeIds !== null && $documentTypeIds !== [] || $hasOrderSettingAttachment) {
            $documentIdOlder = $this->createDocumentWithFile($orderId, $context);
            $documentIdNewer = $this->createDocumentWithFile($orderId, $context);
            $documentIds[] = $documentIdNewer;
        }

        if ($hasOrderSettingAttachment) {
            $event->getContext()->addExtension(
                SendMailAction::MAIL_CONFIG_EXTENSION,
                new MailSendSubscriberConfig(
                    false,
                    $documentIds,
                )
            );
        }

        $transportDecorator = new MailerTransportDecorator(
            $this->createMock(TransportInterface::class),
            $this->getContainer()->get(MailAttachmentsBuilder::class),
            $this->getContainer()->get('shopware.filesystem.public'),
            $this->getContainer()->get('document.repository')
        );
        $mailService = new TestEmailService($this->getContainer()->get(MailFactory::class), $transportDecorator);
        $subscriber = new SendMailAction(
            $mailService,
            $this->getContainer()->get('mail_template.repository'),
            $this->getContainer()->get('logger'),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get('mail_template_type.repository'),
            $this->getContainer()->get(Translator::class),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(LanguageLocaleCodeProvider::class),
            true
        );

        $mailFilterEvent = null;
        $this->getContainer()->get('event_dispatcher')->addListener(FlowSendMailActionEvent::class, static function ($event) use (&$mailFilterEvent): void {
            $mailFilterEvent = $event;
        });

        static::assertIsString($documentIdNewer);
        static::assertIsString($documentIdOlder);
        $criteria = new Criteria(array_filter([$documentIdOlder, $documentIdNewer]));
        $documents = $documentRepository->search($criteria, $context);

        $newDocument = $documents->get($documentIdNewer);
        static::assertNotNull($newDocument);
        static::assertInstanceOf(DocumentEntity::class, $newDocument);
        static::assertFalse($newDocument->getSent());
        $newDocumentOrderVersionId = $newDocument->getOrderVersionId();

        $oldDocument = $documents->get($documentIdOlder);
        static::assertInstanceOf(DocumentEntity::class, $oldDocument);
        static::assertFalse($oldDocument->getSent());
        $oldDocumentOrderVersionId = $oldDocument->getOrderVersionId();

        // new version is created
        static::assertNotEquals($newDocumentOrderVersionId, Defaults::LIVE_VERSION);
        static::assertNotEquals($oldDocumentOrderVersionId, Defaults::LIVE_VERSION);

        $flowFactory = $this->getContainer()->get(FlowFactory::class);
        $flow = $flowFactory->create($event);
        $flow->setConfig($config);

        $subscriber->handleFlow($flow);

        static::assertInstanceOf(FlowSendMailActionEvent::class, $mailFilterEvent);
        static::assertEquals(1, $mailService->calls);
        static::assertIsArray($mailService->data);
        static::assertArrayHasKey('recipients', $mailService->data);

        switch ($recipients['type']) {
            case 'admin':
                $admin = $this->getContainer()->get(Connection::class)->fetchAssociative(
                    'SELECT `first_name`, `last_name`, `email` FROM `user` WHERE `admin` = 1'
                );
                static::assertIsArray($admin);
                static::assertEquals($mailService->data['recipients'], [$admin['email'] => $admin['first_name'] . ' ' . $admin['last_name']]);

                break;
            case 'custom':
                static::assertEquals($mailService->data['recipients'], $recipients['data']);

                break;
            default:
                static::assertEquals($mailService->data['recipients'], [$order->getOrderCustomer()?->getEmail() => $order->getOrderCustomer()?->getFirstName() . ' ' . $order->getOrderCustomer()?->getLastName()]);
        }

        if ($documentTypeIds !== null && $documentTypeIds !== []) {
            $criteria = new Criteria(array_filter([$documentIdOlder, $documentIdNewer]));
            $documents = $documentRepository->search($criteria, $context);

            $newDocument = $documents->get($documentIdNewer);
            static::assertNotNull($newDocument);
            static::assertInstanceOf(DocumentEntity::class, $newDocument);
            static::assertTrue($newDocument->getSent());

            $oldDocument = $documents->get($documentIdOlder);
            static::assertNotNull($oldDocument);
            static::assertInstanceOf(DocumentEntity::class, $oldDocument);
            static::assertFalse($oldDocument->getSent());

            // new document with new version id, old document with old version id
            static::assertEquals($newDocumentOrderVersionId, $newDocument->getOrderVersionId());
            static::assertEquals($oldDocumentOrderVersionId, $oldDocument->getOrderVersionId());
        }
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function sendMailProvider(): iterable
    {
        yield 'Test send mail default' => [['type' => 'customer']];
        yield 'Test send mail admin' => [['type' => 'admin']];
        yield 'Test send mail custom' => [[
            'type' => 'custom',
            'data' => [
                'test2@example.com' => 'Overwrite',
            ],
        ]];
        yield 'Test send mail without attachments' => [['type' => 'customer'], []];
        yield 'Test send mail with attachments from order setting' => [['type' => 'customer'], [], true];
        yield 'Test send mail with attachments from order setting and flow setting ' => [
            ['type' => 'customer'],
            [self::getDocIdByType(DeliveryNoteRenderer::TYPE)],
            true,
        ];
    }

    public function testUpdateMailTemplateTypeWithMailTemplateTypeIdIsNull(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        $context = Context::createDefaultContext();

        $context->addExtension(SendMailAction::MAIL_CONFIG_EXTENSION, new MailSendSubscriberConfig(false, [], []));

        $mailTemplateId = $this->retrieveMailTemplateId();

        $this->getContainer()->get(Connection::class)->executeStatement(
            'UPDATE mail_template SET mail_template_type_id = null WHERE id =:id',
            [
                'id' => Uuid::fromHexToBytes($mailTemplateId),
            ]
        );

        static::assertNotEmpty($mailTemplateId);

        $config = [
            'mailTemplateId' => $mailTemplateId,
            'recipient' => [
                'type' => 'admin',
                'data' => [
                    'phuoc.cao@shopware.com' => 'shopware',
                    'phuoc.cao.x@shopware.com' => 'shopware',
                ],
            ],
        ];

        $event = new ContactFormEvent($context, TestDefaults::SALES_CHANNEL, new MailRecipientStruct(['test@example.com' => 'Shopware ag']), new DataBag());

        $mailService = new TestEmailService();
        $subscriber = new SendMailAction(
            $mailService,
            $this->getContainer()->get('mail_template.repository'),
            $this->getContainer()->get('logger'),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get('mail_template_type.repository'),
            $this->getContainer()->get(Translator::class),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(LanguageLocaleCodeProvider::class),
            true
        );

        $mailFilterEvent = null;
        $this->getContainer()->get('event_dispatcher')->addListener(FlowSendMailActionEvent::class, static function ($event) use (&$mailFilterEvent): void {
            $mailFilterEvent = $event;
        });

        $flowFactory = $this->getContainer()->get(FlowFactory::class);
        $flow = $flowFactory->create($event);
        $flow->setConfig($config);

        $subscriber->handleFlow($flow);

        static::assertIsObject($mailFilterEvent);
        static::assertEquals(1, $mailService->calls);
    }

    #[DataProvider('sendMailContactFormProvider')]
    public function testSendContactFormMail(bool $hasEmail, bool $hasFname, bool $hasLname): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        $context = Context::createDefaultContext();

        $context->addExtension(SendMailAction::MAIL_CONFIG_EXTENSION, new MailSendSubscriberConfig(false, [], []));

        $mailTemplateId = $this->retrieveMailTemplateId();

        $this->getContainer()->get(Connection::class)->executeStatement(
            'UPDATE mail_template SET mail_template_type_id = null WHERE id =:id',
            [
                'id' => Uuid::fromHexToBytes($mailTemplateId),
            ]
        );

        static::assertNotEmpty($mailTemplateId);

        $config = [
            'mailTemplateId' => $mailTemplateId,
            'recipient' => [
                'type' => 'contactFormMail',
            ],
        ];
        $data = new DataBag();
        if ($hasEmail) {
            $data->set('email', 'test@example.com');
        }
        if ($hasFname) {
            $data->set('firstName', 'Shopware');
        }
        if ($hasLname) {
            $data->set('lastName', 'AG');
        }
        $event = new ContactFormEvent($context, TestDefaults::SALES_CHANNEL, new MailRecipientStruct(['test2@example.com' => 'Shopware ag 2']), $data);

        $mailService = new TestEmailService();
        $subscriber = new SendMailAction(
            $mailService,
            $this->getContainer()->get('mail_template.repository'),
            $this->getContainer()->get('logger'),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get('mail_template_type.repository'),
            $this->getContainer()->get(Translator::class),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(LanguageLocaleCodeProvider::class),
            true
        );

        $mailFilterEvent = null;
        $this->getContainer()->get('event_dispatcher')->addListener(FlowSendMailActionEvent::class, static function ($event) use (&$mailFilterEvent): void {
            $mailFilterEvent = $event;
        });

        $flowFactory = $this->getContainer()->get(FlowFactory::class);
        $flow = $flowFactory->create($event);
        $flow->setConfig($config);

        $subscriber->handleFlow($flow);

        if ($hasEmail) {
            static::assertIsArray($mailService->data);
            static::assertArrayHasKey('recipients', $mailService->data);
            static::assertIsObject($mailFilterEvent);
            static::assertEquals(1, $mailService->calls);
            static::assertEquals([$data->get('email') => $data->get('firstName') . ' ' . $data->get('lastName')], $mailService->data['recipients']);
        } else {
            static::assertIsNotObject($mailFilterEvent);
            static::assertEquals(0, $mailService->calls);
        }
    }

    public function testSendContactFormMailType(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        $context = Context::createDefaultContext();

        $context->addExtension(SendMailAction::MAIL_CONFIG_EXTENSION, new MailSendSubscriberConfig(false, [], []));

        $mailTemplateId = $this->retrieveMailTemplateId();

        $this->getContainer()->get(Connection::class)->executeStatement(
            'UPDATE mail_template SET mail_template_type_id = null WHERE id =:id',
            [
                'id' => Uuid::fromHexToBytes($mailTemplateId),
            ]
        );

        static::assertNotEmpty($mailTemplateId);

        $config = [
            'mailTemplateId' => $mailTemplateId,
            'recipient' => [
                'type' => 'contactFormMail',
            ],
        ];

        $customerId = $this->createCustomer($context);
        $orderId = $this->createOrder($customerId, $context);
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('orderCustomer');

        $order = $this->getContainer()->get('order.repository')->search($criteria, $context)->get($orderId);
        static::assertInstanceOf(OrderEntity::class, $order);
        $event = new CheckoutOrderPlacedEvent($context, $order, TestDefaults::SALES_CHANNEL);

        $mailService = new TestEmailService();
        $subscriber = new SendMailAction(
            $mailService,
            $this->getContainer()->get('mail_template.repository'),
            $this->getContainer()->get('logger'),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get('mail_template_type.repository'),
            $this->getContainer()->get(Translator::class),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(LanguageLocaleCodeProvider::class),
            true
        );

        $mailFilterEvent = null;
        $this->getContainer()->get('event_dispatcher')->addListener(FlowSendMailActionEvent::class, static function ($event) use (&$mailFilterEvent): void {
            $mailFilterEvent = $event;
        });

        $flowFactory = $this->getContainer()->get(FlowFactory::class);
        $flow = $flowFactory->create($event);
        $flow->setConfig($config);

        $subscriber->handleFlow($flow);

        static::assertIsNotObject($mailFilterEvent);
        static::assertEquals(0, $mailService->calls);
    }

    /**
     * @return iterable<string, array<int, bool>>
     */
    public static function sendMailContactFormProvider(): iterable
    {
        yield 'Test send mail has data valid' => [true, true, true];
        yield 'Test send mail contact form without email' => [false, true, true];
        yield 'Test send mail contact form without firstName' => [true, false, true];
        yield 'Test send mail contact form without lastName' => [true, false, true];
    }

    public function testSendMailWithConfigIsNull(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        $context = Context::createDefaultContext();

        $context->addExtension(SendMailAction::MAIL_CONFIG_EXTENSION, new MailSendSubscriberConfig(false, [], []));

        $mailTemplateId = $this->retrieveMailTemplateId();

        $config = array_filter([
            'mailTemplateId' => $mailTemplateId,
            'recipient' => [],
        ]);

        $event = new ContactFormEvent($context, TestDefaults::SALES_CHANNEL, new MailRecipientStruct(['test@example.com' => 'Shopware ag']), new DataBag());

        $mailService = new TestEmailService();
        $subscriber = new SendMailAction(
            $mailService,
            $this->getContainer()->get('mail_template.repository'),
            $this->getContainer()->get('logger'),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get('mail_template_type.repository'),
            $this->getContainer()->get(Translator::class),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(LanguageLocaleCodeProvider::class),
            true
        );

        $mailFilterEvent = null;
        $this->getContainer()->get('event_dispatcher')->addListener(FlowSendMailActionEvent::class, static function ($event) use (&$mailFilterEvent): void {
            $mailFilterEvent = $event;
        });

        static::expectException(MailEventConfigurationException::class);
        static::expectExceptionMessage('The recipient value in the flow action configuration is missing.');

        $flowFactory = $this->getContainer()->get(FlowFactory::class);
        $flow = $flowFactory->create($event);
        $flow->setConfig($config);

        $subscriber->handleFlow($flow);

        static::assertIsObject($mailFilterEvent);
        static::assertEquals(1, $mailService->calls);
    }

    #[DataProvider('updateTemplateDataProvider')]
    public function testUpdateTemplateData(bool $shouldUpdate): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        $context = Context::createDefaultContext();

        $mailTemplate = $this->getContainer()
            ->get('mail_template.repository')
            ->search($criteria, $context)
            ->first();

        $this->getContainer()->get(Connection::class)->executeStatement('UPDATE mail_template_type SET template_data = NULL');

        static::assertInstanceOf(MailTemplateEntity::class, $mailTemplate);

        $config = array_filter([
            'mailTemplateId' => $mailTemplate->getId(),
            'recipient' => [
                'type' => 'admin',
                'data' => [
                    'phuoc.cao@shopware.com' => 'shopware',
                    'phuoc.cao.x@shopware.com' => 'shopware',
                ],
            ],
        ]);

        $event = new ContactFormEvent($context, TestDefaults::SALES_CHANNEL, new MailRecipientStruct(['test@example.com' => 'Shopware ag']), new DataBag());

        $mailService = new TestEmailService();

        $subscriber = new SendMailAction(
            $mailService,
            $this->getContainer()->get('mail_template.repository'),
            $this->getContainer()->get('logger'),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get('mail_template_type.repository'),
            $this->getContainer()->get(Translator::class),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(LanguageLocaleCodeProvider::class),
            $shouldUpdate
        );

        $mailFilterEvent = null;
        $this->getContainer()->get('event_dispatcher')->addListener(FlowSendMailActionEvent::class, static function ($event) use (&$mailFilterEvent): void {
            $mailFilterEvent = $event;
        });

        $flowFactory = $this->getContainer()->get(FlowFactory::class);
        $flow = $flowFactory->create($event);
        $flow->setConfig($config);

        $subscriber->handleFlow($flow);

        static::assertIsObject($mailFilterEvent);
        static::assertEquals(1, $mailService->calls);
        static::assertNotNull($mailTemplate->getMailTemplateTypeId());
        $data = $this->getContainer()->get(Connection::class)->fetchOne(
            'SELECT template_data FROM mail_template_type WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($mailTemplate->getMailTemplateTypeId())]
        );

        if ($shouldUpdate) {
            static::assertNotNull($data);
        } else {
            static::assertNull($data);
        }
    }

    public static function updateTemplateDataProvider(): \Generator
    {
        yield 'Test disable mail template updates' => [false];
        yield 'Test enable mail template updates' => [true];
    }

    public function testTranslatorInjectionInMail(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        $context = Context::createDefaultContext();

        $context->addExtension(SendMailAction::MAIL_CONFIG_EXTENSION, new MailSendSubscriberConfig(false, [], []));

        $mailTemplateId = $this->retrieveMailTemplateId();

        $config = array_filter([
            'mailTemplateId' => $mailTemplateId,
            'recipient' => [
                'type' => 'admin',
                'data' => [
                    'phuoc.cao@shopware.com' => 'shopware',
                    'phuoc.cao.x@shopware.com' => 'shopware',
                ],
            ],
        ]);

        $event = new ContactFormEvent($context, TestDefaults::SALES_CHANNEL, new MailRecipientStruct(['test@example.com' => 'Shopware ag']), new DataBag());
        $translator = $this->getContainer()->get(Translator::class);

        $mailService = new TestEmailService();
        $subscriber = new SendMailAction(
            $mailService,
            $this->getContainer()->get('mail_template.repository'),
            $this->getContainer()->get('logger'),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get('mail_template_type.repository'),
            $translator,
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(LanguageLocaleCodeProvider::class),
            true
        );

        $mailFilterEvent = null;
        $snippetSetId = null;
        $function = static function ($event) use (&$mailFilterEvent, $translator, &$snippetSetId): void {
            $mailFilterEvent = $event;
            $snippetSetId = $translator->getSnippetSetId();
        };

        $this->getContainer()->get('event_dispatcher')->addListener(FlowSendMailActionEvent::class, $function);

        $flowFactory = $this->getContainer()->get(FlowFactory::class);
        $flow = $flowFactory->create($event);
        $flow->setConfig($config);

        $subscriber->handleFlow($flow);

        static::assertIsObject($mailFilterEvent);
        static::assertEmpty($translator->getSnippetSetId());
        static::assertNotNull($snippetSetId);
    }

    public function testNumberOfDocumentAttachmentsInCaseFlowSequencesAttachDifferentDocuments(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer($context);
        $orderId = $this->createOrder($customerId, $context);
        $order = $this->orderRepository->search(new Criteria([$orderId]), $context)->getEntities()->first();
        static::assertInstanceOf(OrderEntity::class, $order);

        $documentTypes = $this->connection->fetchAllAssociative(
            'SELECT HEX(`id`) AS `id`, `technical_name` FROM document_type WHERE `technical_name` IN (:type1, :type2);',
            [
                'type1' => InvoiceRenderer::TYPE,
                'type2' => DeliveryNoteRenderer::TYPE,
            ]
        );
        static::assertCount(2, $documentTypes);

        foreach ($documentTypes as $index => $documentType) {
            $generatedDocumentId = $this->createDocumentWithFile($orderId, $context, $documentType['technical_name']);
            $documentTypes[$index]['documentId'] = $generatedDocumentId;

            $criteria = new Criteria([$generatedDocumentId]);
            $criteria->addAssociation('documentMediaFile');
            $document = $this->documentRepository->search($criteria, $context)->getEntities()->first();
            static::assertInstanceOf(DocumentEntity::class, $document);

            $documentMediaFile = $document->getDocumentMediaFile();
            static::assertInstanceOf(MediaEntity::class, $documentMediaFile);
            $documentTypes[$index]['filename'] = $documentMediaFile->getFileName() . '.' . $documentMediaFile->getFileExtension();
        }

        $mailTemplateId = $this->retrieveMailTemplateId();

        $context->addExtension(
            SendMailAction::MAIL_CONFIG_EXTENSION,
            new MailSendSubscriberConfig(
                false,
                [],
                []
            )
        );

        $event = new OrderStateMachineStateChangeEvent('state_enter.order.state.in_progress', $order, $context);
        $flowFactory = $this->getContainer()->get(FlowFactory::class);
        $flow = $flowFactory->create($event);

        $sequencesConfig = $this->createFlowSequencesConfig($mailTemplateId, $documentTypes);

        foreach ($sequencesConfig as $config) {
            $flow->setConfig($config);

            $transportDecorator = new MailerTransportDecorator(
                $this->createMock(TransportInterface::class),
                $this->getContainer()->get(MailAttachmentsBuilder::class),
                $this->getContainer()->get('shopware.filesystem.public'),
                $this->documentRepository
            );

            $mailService = new TestEmailService($this->getContainer()->get(MailFactory::class), $transportDecorator);

            $sendMailAction = new SendMailAction(
                $mailService,
                $this->mailTemplateRepository,
                $this->getContainer()->get('logger'),
                $this->getContainer()->get('event_dispatcher'),
                $this->getContainer()->get('mail_template_type.repository'),
                $this->getContainer()->get(Translator::class),
                $this->connection,
                $this->getContainer()->get(LanguageLocaleCodeProvider::class),
                true
            );

            $sendMailAction->handleFlow($flow);

            static::assertInstanceOf(Email::class, $mailService->mail);
            $attachments = $mailService->mail->getAttachments();

            static::assertCount(\count($config['documentTypeIds']), $attachments);

            foreach ($config['documentTypeIds'] as $sequenzDocumentTypeId) {
                $documentInfos = $this->getMatchingDocument($sequenzDocumentTypeId, $documentTypes);
                static::assertNotEmpty($documentInfos);

                $found = $this->isDocumentPartOfAttachments($attachments, $documentInfos['filename']);
                static::assertTrue($found, 'Attachment not found for document type: ' . $documentInfos['technical_name']);

                $markedAsSent = $this->isDocumentMarkedAsSent($documentInfos['documentId'], $context);
                static::assertTrue($markedAsSent, 'Successfully sent document with id ' . $documentInfos['documentId'] . ' was not marked as sent.');
            }
        }
    }

    private function createCustomer(Context $context): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->getContainer()
            ->get('customer.repository')
            ->upsert([$customer], $context);

        return $customerId;
    }

    private function createOrder(string $customerId, Context $context): string
    {
        $orderId = Uuid::randomHex();
        $stateId = $this->getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE);
        $billingAddressId = Uuid::randomHex();

        $order = [
            'id' => $orderId,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderNumber' => Uuid::randomHex(),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderCustomer' => [
                'customerId' => $customerId,
                'email' => 'test@example.com',
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
            ],
            'stateId' => $stateId,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'billingAddressId' => $billingAddressId,
            'addresses' => [
                [
                    'id' => $billingAddressId,
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                    'countryId' => $this->getValidCountryId(),
                ],
            ],
            'lineItems' => [],
            'deliveries' => [
            ],
            'transactions' => [
                [
                    'paymentMethodId' => $this->getValidPaymentMethodId(),
                    'stateId' => $stateId,
                    'amount' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                ],
            ],
            'context' => '{}',
            'payload' => '{}',
        ];

        $this->orderRepository->upsert([$order], $context);

        return $orderId;
    }

    private function createDocumentWithFile(string $orderId, Context $context, string $documentType = InvoiceRenderer::TYPE): string
    {
        $documentGenerator = $this->getContainer()->get(DocumentGenerator::class);

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, []);
        /** @var DocumentEntity $document */
        $document = $documentGenerator->generate($documentType, [$orderId => $operation], $context)->getSuccess()->first();

        static::assertNotNull($document);

        return $document->getId();
    }

    private static function getDocIdByType(string $documentType): ?string
    {
        $document = KernelLifecycleManager::getConnection()->fetchFirstColumn(
            'SELECT LOWER(HEX(`id`)) FROM `document_type` WHERE `technical_name` = :documentType',
            [
                'documentType' => $documentType,
            ]
        );

        return $document !== [] ? $document[0] : '';
    }

    private function retrieveMailTemplateId(): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        $id = $this->mailTemplateRepository
            ->searchIds($criteria, Context::createDefaultContext())
            ->firstId();

        static::assertIsString($id);

        return $id;
    }

    /**
     * @param array<int, array<string, string>> $documentTypes
     *
     * @return array<array{mailTemplateId: string, documentTypeIds: array<int, string>, recipient: array<string, string|array<string, string>>}>
     */
    private function createFlowSequencesConfig(string $mailTemplateId, array $documentTypes): array
    {
        return [
            [
                'mailTemplateId' => $mailTemplateId,
                'documentTypeIds' => [
                    $documentTypes[0]['id'],
                    $documentTypes[1]['id'],
                ],
                'recipient' => [
                    'type' => 'custom',
                    'data' => [
                        'first@test.com' => 'first recipient',
                    ],
                ],
            ],
            [
                'mailTemplateId' => $mailTemplateId,
                'documentTypeIds' => [
                    $documentTypes[0]['id'],
                ],
                'recipient' => [
                    'type' => 'custom',
                    'data' => [
                        'second@test.com' => 'second recipient',
                    ],
                ],
            ],
            [
                'mailTemplateId' => $mailTemplateId,
                'documentTypeIds' => [
                    $documentTypes[1]['id'],
                ],
                'recipient' => [
                    'type' => 'custom',
                    'data' => [
                        'third@test.com' => 'third recipient',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<array<string, string>> $documentTypes
     *
     * @return array{id: string, technical_name: string, documentId: string, filename: string}|array{}
     */
    private function getMatchingDocument(string $sequenzDocumentTypeId, array $documentTypes): array
    {
        foreach ($documentTypes as $documentType) {
            if ($documentType['id'] === $sequenzDocumentTypeId) {
                return $documentType;
            }
        }

        return [];
    }

    /**
     * @param array<DataPart> $attachments
     */
    private function isDocumentPartOfAttachments(array $attachments, string $documentName): bool
    {
        foreach ($attachments as $attachment) {
            if ($attachment->getFilename() === $documentName) {
                return true;
            }
        }

        return false;
    }

    private function isDocumentMarkedAsSent(string $documentId, Context $context): bool
    {
        $document = $this->documentRepository->search(new Criteria([$documentId]), $context)->getEntities()->first();
        static::assertInstanceOf(DocumentEntity::class, $document);

        return $document->getSent();
    }
}

/**
 * @internal
 */
#[Package('services-settings')]
class TestEmailService extends MailService
{
    public float $calls = 0;

    public ?Email $mail = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = null;

    public function __construct(
        private readonly ?MailFactory $mailFactory = null,
        private readonly ?MailerTransportDecorator $decorator = null
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $templateData
     */
    public function send(array $data, Context $context, array $templateData = []): ?Email
    {
        $this->data = $data;
        ++$this->calls;

        if ($this->mailFactory && $this->decorator) {
            $mail = $this->mailFactory->create(
                $data['subject'],
                ['foo@example.com' => 'foobar'],
                $data['recipients'],
                [],
                [],
                $data,
                $data['binAttachments'] ?? null
            );
            $this->decorator->send($mail);

            $this->mail = $mail;

            return $mail;
        }

        return null;
    }
}
