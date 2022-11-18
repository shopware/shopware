<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Flow;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentGenerator\DeliveryNoteGenerator;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Content\Flow\Dispatching\FlowFactory;
use Shopware\Core\Content\Flow\Dispatching\FlowState;
use Shopware\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Shopware\Core\Content\Mail\Service\MailService as EMailService;
use Shopware\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriber;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
class SendMailActionTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @dataProvider sendMailProvider
     *
     * @param array<string>|null $documentTypeIds
     * @param array<string, mixed> $recipients
     */
    public function testEmailSend(array $recipients, ?array $documentTypeIds = [], ?bool $hasOrderSettingAttachment = true): void
    {
        $documentRepository = $this->getContainer()->get('document.repository');
        $orderRepository = $this->getContainer()->get('order.repository');

        $criteria = new Criteria();
        $criteria->setLimit(1);

        $context = Context::createDefaultContext();

        $customerId = $this->createCustomer($context);
        $orderId = $this->createOrder($customerId, $context);

        $mailTemplateId = $this->getContainer()
            ->get('mail_template.repository')
            ->searchIds($criteria, $context)
            ->firstId();

        static::assertNotNull($mailTemplateId);

        $config = array_filter([
            'mailTemplateId' => $mailTemplateId,
            'recipient' => $recipients,
            'documentTypeIds' => $documentTypeIds,
        ]);

        $order = $orderRepository->search(new Criteria([$orderId]), $context)->first();
        $event = new CheckoutOrderPlacedEvent($context, $order, Defaults::SALES_CHANNEL);

        $documentIdOlder = null;
        $documentIdNewer = null;
        $documentIds = [];

        if (!empty($documentTypeIds) || $hasOrderSettingAttachment) {
            $documentIdOlder = $this->createDocumentWithFile($orderId, $context);
            $documentIdNewer = $this->createDocumentWithFile($orderId, $context);
            $documentIds[] = $documentIdNewer;
        }

        if ($hasOrderSettingAttachment) {
            $event->getContext()->addExtension(
                MailSendSubscriber::MAIL_CONFIG_EXTENSION,
                new MailSendSubscriberConfig(
                    false,
                    $documentIds,
                )
            );
        }

        $mailService = new TestEmailService();
        $subscriber = new SendMailAction(
            $mailService,
            $this->getContainer()->get('mail_template.repository'),
            $this->getContainer()->get(MediaService::class),
            $this->getContainer()->get('media.repository'),
            $this->getContainer()->get('document.repository'),
            $this->getContainer()->get(DocumentService::class),
            $this->getContainer()->get(DocumentGenerator::class),
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
        static::assertNotNull($oldDocument);
        static::assertFalse($oldDocument->getSent());
        $oldDocumentOrderVersionId = $oldDocument->getOrderVersionId();

        // new version is created
        static::assertNotEquals($newDocumentOrderVersionId, Defaults::LIVE_VERSION);
        static::assertNotEquals($oldDocumentOrderVersionId, Defaults::LIVE_VERSION);

        if (!Feature::isActive('v6.5.0.0')) {
            $subscriber->handle(new FlowEvent('action.send.mail', new FlowState($event), $config));
        } else {
            $flowFactory = $this->getContainer()->get(FlowFactory::class);
            $flow = $flowFactory->create($event);
            $flow->setConfig($config);

            $subscriber->handleFlow($flow);
        }

        static::assertIsObject($mailFilterEvent);
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
                static::assertEquals($mailService->data['recipients'], [$order->getOrderCustomer()->getEmail() => $order->getOrderCustomer()->getFirstName() . ' ' . $order->getOrderCustomer()->getLastName()]);
        }

        if (!empty($documentTypeIds)) {
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

            // version does not changed
            static::assertEquals($newDocumentOrderVersionId, $newDocument->getOrderVersionId());
            static::assertEquals($oldDocumentOrderVersionId, $oldDocument->getOrderVersionId());
        }
    }

    /**
     * @return iterable<string, mixed>
     */
    public function sendMailProvider(): iterable
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
            [$this->getDocIdByType(DeliveryNoteRenderer::TYPE)],
            true,
        ];
    }

    public function testUpdateMailTemplateTypeWithMailTemplateTypeIdIsNull(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        $context = Context::createDefaultContext();

        $context->addExtension(MailSendSubscriber::MAIL_CONFIG_EXTENSION, new MailSendSubscriberConfig(false, [], []));

        $mailTemplateId = $this->getContainer()
            ->get('mail_template.repository')
            ->searchIds($criteria, $context)
            ->firstId();

        static::assertNotNull($mailTemplateId);
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

        $event = new ContactFormEvent($context, Defaults::SALES_CHANNEL, new MailRecipientStruct(['test@example.com' => 'Shopware ag']), new DataBag());

        $mailService = new TestEmailService();
        $subscriber = new SendMailAction(
            $mailService,
            $this->getContainer()->get('mail_template.repository'),
            $this->getContainer()->get(MediaService::class),
            $this->getContainer()->get('media.repository'),
            $this->getContainer()->get('document.repository'),
            $this->getContainer()->get(DocumentService::class),
            $this->getContainer()->get(DocumentGenerator::class),
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

        if (!Feature::isActive('v6.5.0.0')) {
            $subscriber->handle(new FlowEvent('test', new FlowState($event), $config));
        } else {
            $flowFactory = $this->getContainer()->get(FlowFactory::class);
            $flow = $flowFactory->create($event);
            $flow->setConfig($config);

            $subscriber->handleFlow($flow);
        }

        static::assertIsObject($mailFilterEvent);
        static::assertEquals(1, $mailService->calls);
    }

    /**
     * @dataProvider sendMailContactFormProvider
     */
    public function testSendContactFormMail(bool $hasEmail, bool $hasFname, bool $hasLname): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        $context = Context::createDefaultContext();

        $context->addExtension(MailSendSubscriber::MAIL_CONFIG_EXTENSION, new MailSendSubscriberConfig(false, [], []));

        $mailTemplateId = $this->getContainer()
            ->get('mail_template.repository')
            ->searchIds($criteria, $context)
            ->firstId();

        static::assertNotNull($mailTemplateId);
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
        $event = new ContactFormEvent($context, Defaults::SALES_CHANNEL, new MailRecipientStruct(['test2@example.com' => 'Shopware ag 2']), $data);

        $mailService = new TestEmailService();
        $subscriber = new SendMailAction(
            $mailService,
            $this->getContainer()->get('mail_template.repository'),
            $this->getContainer()->get(MediaService::class),
            $this->getContainer()->get('media.repository'),
            $this->getContainer()->get('document.repository'),
            $this->getContainer()->get(DocumentService::class),
            $this->getContainer()->get(DocumentGenerator::class),
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

        if (!Feature::isActive('v6.5.0.0')) {
            $subscriber->handle(new FlowEvent('test', new FlowState($event), $config));
        } else {
            $flowFactory = $this->getContainer()->get(FlowFactory::class);
            $flow = $flowFactory->create($event);
            $flow->setConfig($config);

            $subscriber->handleFlow($flow);
        }

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

        $context->addExtension(MailSendSubscriber::MAIL_CONFIG_EXTENSION, new MailSendSubscriberConfig(false, [], []));

        $mailTemplateId = $this->getContainer()
            ->get('mail_template.repository')
            ->searchIds($criteria, $context)
            ->firstId();

        static::assertNotNull($mailTemplateId);
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
        $event = new CheckoutOrderPlacedEvent($context, $order, Defaults::SALES_CHANNEL);

        $mailService = new TestEmailService();
        $subscriber = new SendMailAction(
            $mailService,
            $this->getContainer()->get('mail_template.repository'),
            $this->getContainer()->get(MediaService::class),
            $this->getContainer()->get('media.repository'),
            $this->getContainer()->get('document.repository'),
            $this->getContainer()->get(DocumentService::class),
            $this->getContainer()->get(DocumentGenerator::class),
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

        if (!Feature::isActive('v6.5.0.0')) {
            $subscriber->handle(new FlowEvent('test', new FlowState($event), $config));
        } else {
            $flowFactory = $this->getContainer()->get(FlowFactory::class);
            $flow = $flowFactory->create($event);
            $flow->setConfig($config);

            $subscriber->handleFlow($flow);
        }

        static::assertIsNotObject($mailFilterEvent);
        static::assertEquals(0, $mailService->calls);
    }

    /**
     * @return iterable<string, array<int, bool>>
     */
    public function sendMailContactFormProvider(): iterable
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

        $context->addExtension(MailSendSubscriber::MAIL_CONFIG_EXTENSION, new MailSendSubscriberConfig(false, [], []));

        $mailTemplateId = $this->getContainer()
            ->get('mail_template.repository')
            ->searchIds($criteria, $context)
            ->firstId();

        static::assertNotNull($mailTemplateId);

        $config = array_filter([
            'mailTemplateId' => $mailTemplateId,
            'recipient' => [],
        ]);

        $event = new ContactFormEvent($context, Defaults::SALES_CHANNEL, new MailRecipientStruct(['test@example.com' => 'Shopware ag']), new DataBag());

        $mailService = new TestEmailService();
        $subscriber = new SendMailAction(
            $mailService,
            $this->getContainer()->get('mail_template.repository'),
            $this->getContainer()->get(MediaService::class),
            $this->getContainer()->get('media.repository'),
            $this->getContainer()->get('document.repository'),
            $this->getContainer()->get(DocumentService::class),
            $this->getContainer()->get(DocumentGenerator::class),
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

        if (!Feature::isActive('v6.5.0.0')) {
            $subscriber->handle(new FlowEvent('test', new FlowState($event), $config));
        } else {
            $flowFactory = $this->getContainer()->get(FlowFactory::class);
            $flow = $flowFactory->create($event);
            $flow->setConfig($config);

            $subscriber->handleFlow($flow);
        }

        static::assertIsObject($mailFilterEvent);
        static::assertEquals(1, $mailService->calls);
    }

    /**
     * @dataProvider updateTemplateDataProvider
     */
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

        $event = new ContactFormEvent($context, Defaults::SALES_CHANNEL, new MailRecipientStruct(['test@example.com' => 'Shopware ag']), new DataBag());

        $mailService = new TestEmailService();

        $subscriber = new SendMailAction(
            $mailService,
            $this->getContainer()->get('mail_template.repository'),
            $this->getContainer()->get(MediaService::class),
            $this->getContainer()->get('media.repository'),
            $this->getContainer()->get('document.repository'),
            $this->getContainer()->get(DocumentService::class),
            $this->getContainer()->get(DocumentGenerator::class),
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

        if (!Feature::isActive('v6.5.0.0')) {
            $subscriber->handle(new FlowEvent('test', new FlowState($event), $config));
        } else {
            $flowFactory = $this->getContainer()->get(FlowFactory::class);
            $flow = $flowFactory->create($event);
            $flow->setConfig($config);

            $subscriber->handleFlow($flow);
        }

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

    public function updateTemplateDataProvider(): \Generator
    {
        yield 'Test disable mail template updates' => [false];
        yield 'Test enable mail template updates' => [true];
    }

    public function testTranslatorInjectionInMail(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        $context = Context::createDefaultContext();

        $context->addExtension(MailSendSubscriber::MAIL_CONFIG_EXTENSION, new MailSendSubscriberConfig(false, [], []));

        $mailTemplateId = $this->getContainer()
            ->get('mail_template.repository')
            ->searchIds($criteria, $context)
            ->firstId();

        static::assertNotNull($mailTemplateId);

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

        $event = new ContactFormEvent($context, Defaults::SALES_CHANNEL, new MailRecipientStruct(['test@example.com' => 'Shopware ag']), new DataBag());
        $translator = $this->getContainer()->get(Translator::class);

        if ($translator->getSnippetSetId()) {
            $translator->resetInjection();
        }

        $mailService = new TestEmailService();
        $subscriber = new SendMailAction(
            $mailService,
            $this->getContainer()->get('mail_template.repository'),
            $this->getContainer()->get(MediaService::class),
            $this->getContainer()->get('media.repository'),
            $this->getContainer()->get('document.repository'),
            $this->getContainer()->get(DocumentService::class),
            $this->getContainer()->get(DocumentGenerator::class),
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

        if (!Feature::isActive('v6.5.0.0')) {
            $subscriber->handle(new FlowEvent('test', new FlowState($event), $config));
        } else {
            $flowFactory = $this->getContainer()->get(FlowFactory::class);
            $flow = $flowFactory->create($event);
            $flow->setConfig($config);

            $subscriber->handleFlow($flow);
        }

        static::assertIsObject($mailFilterEvent);
        static::assertEmpty($translator->getSnippetSetId());
        static::assertNotNull($snippetSetId);
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
            'salesChannelId' => Defaults::SALES_CHANNEL,
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
            'salesChannelId' => Defaults::SALES_CHANNEL,
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
            'context' => '{}',
            'payload' => '{}',
        ];

        $orderRepository = $this->getContainer()->get('order.repository');

        $orderRepository->upsert([$order], $context);

        return $orderId;
    }

    private function createDocumentWithFile(string $orderId, Context $context, string $documentType = InvoiceRenderer::TYPE): string
    {
        if (Feature::isActive('v6.5.0.0')) {
            $documentGenerator = $this->getContainer()->get(DocumentGenerator::class);

            $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, []);
            $document = $documentGenerator->generate($documentType, [$orderId => $operation], $context)->getSuccess()->first();

            static::assertNotNull($document);

            return $document->getId();
        }

        $documentService = $this->getContainer()->get(DocumentService::class);

        $documentStruct = $documentService->create(
            $orderId,
            DeliveryNoteGenerator::DELIVERY_NOTE,
            FileTypes::PDF,
            new DocumentConfiguration(),
            $context
        );

        return $documentStruct->getId();
    }

    private function getDocIdByType(string $documentType): ?string
    {
        $document = $this->getContainer()->get(Connection::class)->fetchFirstColumn(
            'SELECT LOWER(HEX(`id`)) FROM `document_type` WHERE `technical_name` = :documentType',
            [
                'documentType' => $documentType,
            ]
        );

        return $document ? $document[0] : '';
    }
}

/**
 * @internal
 */
class TestMailSendSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            MailBeforeValidateEvent::class => 'sendMail',
        ];
    }

    public function sendMail(MailBeforeValidateEvent $event): void
    {
        $event->addTemplateData('myTestAddTemplateKey', 'myTestAddTemplateValue');
        $templateData = $event->getTemplateData();
        $templateData['myTestTemplateKey'] = 'myTestTemplateValue';
        $event->setTemplateData($templateData);

        $event->addData('myTestAddKey', 'myTestAddValue');
        $data = $event->getData();
        $data['myTestKey'] = 'myTestValue';
        $event->setData($data);
    }
}

/**
 * @internal
 */
class TestStopSendSubscriber implements EventSubscriberInterface
{
    /**
     * @var MailBeforeSentEvent
     */
    public $event;

    public static function getSubscribedEvents(): array
    {
        return [
            MailBeforeSentEvent::class => 'doNotSent',
        ];
    }

    public function doNotSent(MailBeforeSentEvent $event): void
    {
        $this->event = $event;
        $event->stopPropagation();
    }
}

/**
 * @internal
 */
class TestEmailService extends EMailService
{
    public float $calls = 0;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = null;

    public function __construct()
    {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $templateData
     */
    public function send(array $data, Context $context, array $templateData = []): ?Email
    {
        $this->data = $data;
        ++$this->calls;

        return null;
    }
}
