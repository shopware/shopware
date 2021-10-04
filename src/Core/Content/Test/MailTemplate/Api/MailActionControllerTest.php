<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\MailTemplate\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\DataCollector\MessageDataCollector;
use Symfony\Component\Mime\Email;

/**
 * @group slow
 */
class MailActionControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use MediaFixtures;

    private const MEDIA_FIXTURE = __DIR__ . '/../../Media/fixtures/small.pdf';

    private StateMachineRegistry $stateMachineRegistry;

    public function setUp(): void
    {
        static::markTestSkipped('to heavy memory usage - if you changed something for mails, run this');
        $this->stateMachineRegistry = $this->getContainer()->get(StateMachineRegistry::class);
        $this->getContainer()->get('profiler')->enable();
    }

    protected function tearDown(): void
    {
        $this->getContainer()->get('profiler')->disable();
    }

    public function testSendingSimpleTestMail(): void
    {
        $data = $this->getTestData();

        $this->getBrowser()->request('POST', '/api/_action/mail-template/send', $data);

        // check response status code
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        /** @var MessageDataCollector $mailCollector */
        $mailCollector = $this->getBrowser()->getProfile()->getCollector('mailer');

        // checks that an email was sent
        $messages = $mailCollector->getEvents()->getMessages();
        static::assertGreaterThan(0, \count($messages));
        /** @var Email $message */
        $message = array_pop($messages);

        // Asserting email data
        static::assertInstanceOf(Email::class, $message);
        static::assertSame('My precious subject', $message->getSubject());
        static::assertSame(
            'doNotReply@localhost.com',
            current($message->getFrom())->getAddress(),
            print_r($message->getFrom(), true)
        );
        static::assertSame('No Reply', current($message->getFrom())->getName(), print_r($message->getFrom(), true));
        static::assertSame(
            'recipient@example.com',
            current($message->getTo())->getAddress(),
            print_r($message->getFrom(), true)
        );

        $partsByType = [];
        $partsByType['text/plain'] = $message->getTextBody();
        $partsByType['text/html'] = $message->getHtmlBody();

        static::assertSame('This is plain text', $partsByType['text/plain']);
        static::assertSame('<h1>This is HTML</h1>', $partsByType['text/html']);
    }

    public function testSendingMailWithMailTemplateData(): void
    {
        $data = $this->getTestData();
        $data['contentHtml'] = '<span>{{ order.deliveries.0.trackingCodes.0 }}</span><span>{{ order.deliveries.1.trackingCodes.1 }}</span>';
        $data['testMode'] = true;
        $data['mailTemplateData'] = [
            'order' => [
                'salesChannel' => [],
                'deepLinkCode' => 'home',
                'deliveries' => [
                    0 => [
                        'trackingCodes' => ['324fsdf', '1234sdf'],
                    ],
                    1 => [
                        'trackingCodes' => ['dfvv456', '435x4'],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/_action/mail-template/send', $data);

        // check response status code
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        /** @var MessageDataCollector $mailCollector */
        $mailCollector = $this->getBrowser()->getProfile()->getCollector('mailer');

        // checks that an email was sent
        $messages = $mailCollector->getEvents()->getMessages();
        static::assertGreaterThan(0, \count($messages));
        /** @var Email $message */
        $message = array_pop($messages);

        $partsByType = [];
        $partsByType['text/html'] = $message->getHtmlBody();

        static::assertSame('<span>324fsdf</span><span>435x4</span>', $partsByType['text/html']);
    }

    public function testSendingMailWithAttachments(): void
    {
        $data = $this->getTestDataWithAttachments();

        $this->getBrowser()->request('POST', '/api/_action/mail-template/send', $data);

        // check response status code
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        /** @var MessageDataCollector $mailCollector */
        $mailCollector = $this->getBrowser()->getProfile()->getCollector('mailer');

        // checks that an email was sent
        $messages = $mailCollector->getEvents()->getMessages();
        static::assertGreaterThan(0, \count($messages));
        /** @var Email $message */
        $message = array_pop($messages);

        // Asserting email data
        static::assertInstanceOf(Email::class, $message);

        $partsByType = [];
        $partsByType['application/pdf'] = $message->getAttachments()[0];

        static::assertArrayHasKey('application/pdf', $partsByType);

        // Use strcmp() for binary safety
        static::assertSame(0, strcmp($partsByType['application/pdf']->getBody(), file_get_contents(self::MEDIA_FIXTURE)));
    }

    public function testSendingMailWithFooterAndHeader(): void
    {
        $data = $this->getTestDataWithHeaderAndFooter();

        $this->getBrowser()->request('POST', '/api/_action/mail-template/send', $data);

        // check response status code
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        /** @var MessageDataCollector $mailCollector */
        $mailCollector = $this->getBrowser()->getProfile()->getCollector('mailer');

        // checks that an email was sent
        $messages = $mailCollector->getEvents()->getMessages();
        static::assertGreaterThan(0, \count($messages));
        /** @var Email $message */
        $message = array_pop($messages);

        // Asserting email data
        static::assertInstanceOf(Email::class, $message);

        $partsByType = [];
        $partsByType['text/plain'] = $message->getTextBody();
        $partsByType['text/html'] = $message->getHtmlBody();

        static::assertSame('Header This is plain text Footer', $partsByType['text/plain']);
        static::assertSame('<h1>Header</h1> <h1>This is HTML</h1> <h1>Footer</h1>', $partsByType['text/html']);
    }

    public function testSendingMailWithAutomaticDocumentAttachments(): void
    {
        if (!Feature::isActive('FEATURE_NEXT_7530')) {
            static::markTestSkipped('Test can only run with feature flag FEATURE_NEXT_7530');
        }

        $data = $this->getTestData();
        $data['documentIds'] = [$this->getDocumentId()];

        $this->getBrowser()->request('POST', '/api/_action/mail-template/send', $data);

        // check response status code
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        /** @var MessageDataCollector $mailCollector */
        $mailCollector = $this->getBrowser()->getProfile()->getCollector('mailer');

        // checks that an email was sent
        $messages = $mailCollector->getEvents()->getMessages();
        static::assertGreaterThan(0, \count($messages));
        /** @var Email $message */
        $message = array_pop($messages);

        // Asserting email data
        static::assertInstanceOf(Email::class, $message);

        $partsByType = [];
        $partsByType['application/pdf'] = $message->getAttachments()[0];

        static::assertArrayHasKey('application/pdf', $partsByType);

        // Use strcmp() for binary safety
        static::assertSame(0, strcmp($partsByType['application/pdf']->getBody(), file_get_contents(self::MEDIA_FIXTURE)));
    }

    public function testBuildingRenderedMailTemplate(): void
    {
        $data = $this->getTestDataWithMailTemplateType();

        $this->getBrowser()->request('POST', '/api/_action/mail-template/build', $data);

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertSame('<h1>This is HTML</h1>', json_decode($this->getBrowser()->getResponse()->getContent()));
    }

    private function getTestData(): array
    {
        return [
            'recipients' => ['recipient@example.com' => 'Recipient'],
            'contentPlain' => 'This is plain text',
            'contentHtml' => '<h1>This is HTML</h1>',
            'subject' => 'My precious subject',
            'senderName' => 'No Reply',
            'mediaIds' => [],
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
        ];
    }

    private function getTestDataWithMailTemplateType(): array
    {
        $testData['mailTemplateType'] = [
            'templateData' => [
                'salesChannel' => [
                    'id' => TestDefaults::SALES_CHANNEL,
                ],
            ],
        ];
        $testData['mailTemplate'] = [
            'contentPlain' => 'This is plain text',
            'contentHtml' => '<h1>This is HTML</h1>',
        ];

        return $testData;
    }

    private function getTestDataWithAttachments(): array
    {
        $testData = $this->getTestData();
        $mediaFixture = $this->preparePdfMediaFixture();
        $testData['mediaIds'] = [$mediaFixture->getId()];

        return $testData;
    }

    private function getTestDataWithHeaderAndFooter(): array
    {
        $testData = $this->getTestData();
        $this->createHeaderAndFooter();

        return $testData;
    }

    private function preparePdfMediaFixture(): MediaEntity
    {
        $mediaFixture = $this->getPdf();
        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);

        $this->getPublicFilesystem()->put(
            $urlGenerator->getRelativeMediaUrl($mediaFixture),
            file_get_contents(self::MEDIA_FIXTURE)
        );

        return $mediaFixture;
    }

    private function createHeaderAndFooter(): void
    {
        $headerFooterRepository = $this->getContainer()->get('mail_header_footer.repository');

        $data = [
            'id' => Uuid::randomHex(),
            'systemDefault' => true,
            'name' => 'Test-Template',
            'description' => 'John Doe',
            'headerPlain' => 'Header ',
            'headerHtml' => '<h1>Header</h1> ',
            'footerPlain' => ' Footer',
            'footerHtml' => ' <h1>Footer</h1>',
            'salesChannels' => [
                [
                    'id' => TestDefaults::SALES_CHANNEL,
                ],
            ],
        ];

        $headerFooterRepository->create([$data], Context::createDefaultContext());
    }

    private function getDocumentId(): string
    {
        /** @var EntityRepositoryInterface $documentRepo */
        $documentRepo = $this->getContainer()->get('document.repository');
        $context = Context::createDefaultContext();
        $orderId = $this->getOrderId($context);
        $documentId = Uuid::randomHex();
        $mediaEntity = $this->preparePdfMediaFixture();

        $documentRepo->create(
            [
                [
                    'id' => $documentId,
                    'documentTypeId' => $this->getValidDocumentTypeId(),
                    'fileType' => 'pdf',
                    'orderId' => $orderId,
                    'config' => [],
                    'deepLinkCode' => Uuid::randomHex(),
                    'documentMediaFile' => [
                        'id' => $mediaEntity->getId(),
                    ],
                ],
            ],
            $context
        );

        return $documentId;
    }

    private function getOrderId(Context $context): string
    {
        /** @var EntityRepositoryInterface $orderRepo */
        $orderRepo = $this->getContainer()->get('order.repository');
        $orderId = Uuid::randomHex();
        $addressId = Uuid::randomHex();
        $countryStateId = Uuid::randomHex();
        $salutation = $this->getValidSalutationId();

        $orderRepo->create(
            [
                [
                    'id' => $orderId,
                    'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
                    'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'stateId' => $this->stateMachineRegistry->getInitialState(OrderStates::STATE_MACHINE, $context)->getId(),
                    'paymentMethodId' => $this->getValidPaymentMethodId(),
                    'currencyId' => Defaults::CURRENCY,
                    'currencyFactor' => 1,
                    'salesChannelId' => Defaults::SALES_CHANNEL,
                    'transactions' => [
                        [
                            'id' => Uuid::randomHex(),
                            'paymentMethodId' => $this->getValidPaymentMethodId(),
                            'stateId' => $this->getStateMachineState(OrderTransactionStates::STATE_MACHINE, OrderTransactionStates::STATE_OPEN),
                            'amount' => [
                                'unitPrice' => 5.0,
                                'totalPrice' => 15.0,
                                'quantity' => 3,
                                'calculatedTaxes' => [],
                                'taxRules' => [],
                            ],
                        ],
                    ],
                    'deliveries' => [
                        [
                            'stateId' => $this->stateMachineRegistry->getInitialState(OrderDeliveryStates::STATE_MACHINE, $context)->getId(),
                            'shippingMethodId' => $this->getValidShippingMethodId(),
                            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                            'shippingDateEarliest' => date(\DATE_ISO8601),
                            'shippingDateLatest' => date(\DATE_ISO8601),
                            'shippingOrderAddress' => [
                                'salutationId' => $salutation,
                                'firstName' => 'Floy',
                                'lastName' => 'Glover',
                                'zipcode' => '59438-0403',
                                'city' => 'Stellaberg',
                                'street' => 'street',
                                'country' => [
                                    'name' => 'kasachstan',
                                    'id' => $this->getValidCountryId(),
                                ],
                            ],
                        ],
                    ],
                    'lineItems' => [],
                    'deepLinkCode' => 'BwvdEInxOHBbwfRw6oHF1Q_orfYeo9RY',
                    'orderCustomer' => [
                        'email' => 'test@example.com',
                        'firstName' => 'Noe',
                        'lastName' => 'Hill',
                        'salutationId' => $salutation,
                        'title' => 'Doc',
                        'customerNumber' => 'Test',
                        'customer' => [
                            'email' => 'test@example.com',
                            'firstName' => 'Noe',
                            'lastName' => 'Hill',
                            'salutationId' => $salutation,
                            'title' => 'Doc',
                            'customerNumber' => 'Test',
                            'guest' => true,
                            'group' => ['name' => 'testse2323'],
                            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                            'salesChannelId' => Defaults::SALES_CHANNEL,
                            'defaultBillingAddressId' => $addressId,
                            'defaultShippingAddressId' => $addressId,
                            'addresses' => [
                                [
                                    'id' => $addressId,
                                    'salutationId' => $salutation,
                                    'firstName' => 'Floy',
                                    'lastName' => 'Glover',
                                    'zipcode' => '59438-0403',
                                    'city' => 'Stellaberg',
                                    'street' => 'street',
                                    'countryStateId' => $countryStateId,
                                    'country' => [
                                        'name' => 'kasachstan',
                                        'id' => $this->getValidCountryId(),
                                        'states' => [
                                            [
                                                'id' => $countryStateId,
                                                'name' => 'oklahoma',
                                                'shortCode' => 'OH',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'billingAddressId' => $addressId,
                    'addresses' => [
                        [
                            'salutationId' => $salutation,
                            'firstName' => 'Floy',
                            'lastName' => 'Glover',
                            'zipcode' => '59438-0403',
                            'city' => 'Stellaberg',
                            'street' => 'street',
                            'countryId' => $this->getValidCountryId(),
                            'id' => $addressId,
                        ],
                    ],
                ],
            ],
            $context
        );

        return $orderId;
    }
}
