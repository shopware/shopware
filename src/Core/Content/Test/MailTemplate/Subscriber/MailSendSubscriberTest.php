<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\MailTemplate\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentGenerator\DeliveryNoteGenerator;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Content\MailTemplate\Service\MailService;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriber;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\BusinessEvent;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

class MailSendSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @dataProvider sendMailProvider
     */
    public function testSendMail(bool $skip, ?array $recipients, array $expectedRecipients): void
    {
        $documentRepository = $this->getContainer()->get('document.repository');

        $criteria = new Criteria();
        $criteria->setLimit(1);

        $context = Context::createDefaultContext();

        $customerId = $this->createCustomer($context);
        $orderId = $this->createOrder($customerId, $context);
        $documentId = $this->createDocumentWithFile($orderId, $context);

        $context->addExtension(MailSendSubscriber::MAIL_CONFIG_EXTENSION, new MailSendSubscriberConfig($skip, [$documentId], []));

        $mailTemplateId = $this->getContainer()
            ->get('mail_template.repository')
            ->searchIds($criteria, $context)
            ->firstId();

        static::assertNotEmpty($mailTemplateId);

        $config = array_filter([
            'mail_template_id' => $mailTemplateId,
            'recipients' => $recipients,
        ]);

        $event = new ContactFormEvent($context, Defaults::SALES_CHANNEL, new MailRecipientStruct(['test@example.com' => 'Shopware ag']), new DataBag());

        $mailService = new TestMailService();
        $subscriber = new MailSendSubscriber(
            $mailService,
            $this->getContainer()->get('mail_template.repository'),
            $this->getContainer()->get(MediaService::class),
            $this->getContainer()->get('media.repository'),
            $this->getContainer()->get('document.repository'),
            $this->getContainer()->get(DocumentService::class),
            $this->getContainer()->get('logger')
        );

        $subscriber->sendMail(new BusinessEvent('test', $event, $config));

        if ($skip) {
            static::assertEquals(0, $mailService->calls);
            static::assertNull($mailService->data);
        } else {
            static::assertEquals(1, $mailService->calls);
            static::assertEquals($mailService->data['recipients'], $expectedRecipients);

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('id', $documentId))->addFilter(new EqualsFilter('sent', true));
            $document = $documentRepository->search($criteria, $context)->first();
            static::assertNotNull($document);
        }
    }

    public function sendMailProvider(): iterable
    {
        yield 'Test skip mail' => [true, null, ['test@example.com' => 'Shopware ag']];
        yield 'Test send mail' => [false, null, ['test@example.com' => 'Shopware ag']];
        yield 'Test overwrite recipients' => [false, ['test2@example.com' => 'Overwrite'], ['test2@example.com' => 'Overwrite']];
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
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
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
        $stateId = $this->getContainer()->get(StateMachineRegistry::class)->getInitialState(OrderStates::STATE_MACHINE, $context)->getId();
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
        /** @var DocumentService $documentService */
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
}

class TestMailService extends MailService
{
    public $calls = 0;

    public $data = null;

    public function __construct()
    {
    }

    public function send(array $data, Context $context, array $templateData = []): ?\Swift_Message
    {
        $this->data = $data;
        ++$this->calls;

        return null;
    }
}
