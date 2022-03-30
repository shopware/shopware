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
use Shopware\Core\Checkout\Document\DocumentGenerator\DeliveryNoteGenerator;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
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
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SendMailActionTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @dataProvider sendMailProvider
     */
    public function testEmailSend(array $recipients, ?bool $hasFlowSettingAttachment = true, ?bool $hasOrderSettingAttachment = true): void
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

        static::assertNotEmpty($mailTemplateId);

        $config = array_filter([
            'mailTemplateId' => $mailTemplateId,
            'recipient' => $recipients,
            'documentTypeIds' => $hasFlowSettingAttachment ? [$this->getDocIdByType(DeliveryNoteGenerator::DELIVERY_NOTE)] : [],
        ]);

        $order = $orderRepository->search(new Criteria([$orderId]), $context)->first();
        $event = new CheckoutOrderPlacedEvent($context, $order, Defaults::SALES_CHANNEL);

        if ($hasFlowSettingAttachment || $hasOrderSettingAttachment) {
            $documentIdOlder = $this->createDocumentWithFile($orderId, $context);
            $documentIdNewer = $this->createDocumentWithFile($orderId, $context);
        }

        if ($hasOrderSettingAttachment) {
            $event->getContext()->addExtension(
                MailSendSubscriber::MAIL_CONFIG_EXTENSION,
                new MailSendSubscriberConfig(
                    false,
                    [$documentIdNewer],
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

        $subscriber->handle(new FlowEvent('action.send.mail', new FlowState($event), $config));

        static::assertIsObject($mailFilterEvent);
        static::assertEquals(1, $mailService->calls);

        switch ($recipients['type']) {
            case 'admin':
                $admin = $this->getContainer()->get(Connection::class)->fetchAssociative(
                    'SELECT `first_name`, `last_name`, `email` FROM `user` WHERE `admin` = 1'
                );
                static::assertEquals($mailService->data['recipients'], [$admin['email'] => $admin['first_name'] . ' ' . $admin['last_name']]);

                break;
            case 'custom':
                static::assertEquals($mailService->data['recipients'], $recipients['data']);

                break;
            default:
                static::assertEquals($mailService->data['recipients'], [$order->getOrderCustomer()->getEmail() => $order->getOrderCustomer()->getFirstName() . ' ' . $order->getOrderCustomer()->getLastName()]);
        }

        if ($hasFlowSettingAttachment) {
            $criteria = new Criteria([$documentIdOlder, $documentIdNewer]);
            $documents = $documentRepository->search($criteria, $context);
            foreach ($documents as $document) {
                if ($document->getSent()) {
                    static::assertEquals($documentIdNewer, $document->getId());
                } else {
                    static::assertEquals($documentIdOlder, $document->getId());
                }
            }
        }
    }

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
        yield 'Test send mail without attachments' => [['type' => 'customer'], false];
        yield 'Test send mail with attachments from order setting' => [['type' => 'customer'], false, true];
        yield 'Test send mail with attachments from order setting and flow setting ' => [['type' => 'customer'], true, true];
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

        $this->getContainer()->get(Connection::class)->executeStatement(
            'UPDATE mail_template SET mail_template_type_id = null WHERE id =:id',
            [
                'id' => Uuid::fromHexToBytes($mailTemplateId),
            ]
        );

        static::assertNotEmpty($mailTemplateId);

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

        $mailService = new TestEmailService();
        $subscriber = new SendMailAction(
            $mailService,
            $this->getContainer()->get('mail_template.repository'),
            $this->getContainer()->get(MediaService::class),
            $this->getContainer()->get('media.repository'),
            $this->getContainer()->get('document.repository'),
            $this->getContainer()->get(DocumentService::class),
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

        $subscriber->handle(new FlowEvent('test', new FlowState($event), $config));

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

        $this->getContainer()->get(Connection::class)->executeStatement(
            'UPDATE mail_template SET mail_template_type_id = null WHERE id =:id',
            [
                'id' => Uuid::fromHexToBytes($mailTemplateId),
            ]
        );

        static::assertNotEmpty($mailTemplateId);

        $config = array_filter([
            'mailTemplateId' => $mailTemplateId,
            'recipient' => [
                'type' => 'contactFormMail',
            ],
        ]);
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

        $subscriber->handle(new FlowEvent('test', new FlowState($event), $config));

        if ($hasEmail) {
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

        $this->getContainer()->get(Connection::class)->executeStatement(
            'UPDATE mail_template SET mail_template_type_id = null WHERE id =:id',
            [
                'id' => Uuid::fromHexToBytes($mailTemplateId),
            ]
        );

        static::assertNotEmpty($mailTemplateId);

        $config = array_filter([
            'mailTemplateId' => $mailTemplateId,
            'recipient' => [
                'type' => 'contactFormMail',
            ],
        ]);

        $event = new CheckoutOrderPlacedEvent($context, new OrderEntity(), Defaults::SALES_CHANNEL);

        $mailService = new TestEmailService();
        $subscriber = new SendMailAction(
            $mailService,
            $this->getContainer()->get('mail_template.repository'),
            $this->getContainer()->get(MediaService::class),
            $this->getContainer()->get('media.repository'),
            $this->getContainer()->get('document.repository'),
            $this->getContainer()->get(DocumentService::class),
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

        $subscriber->handle(new FlowEvent('test', new FlowState($event), $config));

        static::assertIsNotObject($mailFilterEvent);
        static::assertEquals(0, $mailService->calls);
    }

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

        static::assertNotEmpty($mailTemplateId);

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
        $subscriber->handle(new FlowEvent('test', new FlowState($event), $config));

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

        $subscriber->handle(new FlowEvent('test', new FlowState($event), $config));

        static::assertIsObject($mailFilterEvent);
        static::assertEquals(1, $mailService->calls);

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

    public function updateTemplateDataProvider()
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

        static::assertNotEmpty($mailTemplateId);

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

        $subscriber->handle(new FlowEvent('test', new FlowState($event), $config));

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

    private function createDocumentWithFile(string $orderId, Context $context): string
    {
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

class TestStringTemplateRenderer extends StringTemplateRenderer
{
    /**
     * @var array
     */
    public $templateData;

    public function __construct()
    {
    }

    public function initialize(): void
    {
    }

    public function render(string $templateSource, array $data, Context $context): string
    {
        $this->templateData = $data;

        return '';
    }

    public function enableTestMode(): void
    {
    }

    public function disableTestMode(): void
    {
    }
}

class TestEmailService extends EMailService
{
    public $calls = 0;

    public $data = null;

    public function __construct()
    {
    }

    public function send(array $data, Context $context, array $templateData = []): ?\Symfony\Component\Mime\Email
    {
        $this->data = $data;
        ++$this->calls;

        return null;
    }
}
