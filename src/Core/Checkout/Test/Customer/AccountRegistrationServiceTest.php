<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\Event\DoubleOptInGuestOrderEvent;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountRegistrationService;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcher;

class AccountRegistrationServiceTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;
    use MailTemplateTestBehaviour;

    /**
     * @var AccountRegistrationService|null
     */
    private $accountRegistrationService;

    protected function setUp(): void
    {
        $this->accountRegistrationService = $this->getContainer()->get(AccountRegistrationService::class);
    }

    public function testRegister(): void
    {
        /** @var SalesChannelContextFactory $salesChannelContextFactory */
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $this->assignMailtemplatesToSalesChannel(Defaults::SALES_CHANNEL, $salesChannelContext->getContext());

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $customerRegisterData = $this->getCustomerRegisterData();

        $dataBag = new DataBag();
        $dataBag->add($customerRegisterData);

        $phpunit = $this;

        $mailEventDidRun = false;
        $listenerMailEventClosure = function (MailSentEvent $event) use (&$mailEventDidRun, $phpunit, $dataBag): void {
            $mailEventDidRun = true;
            $phpunit->assertStringContainsString('Dear Mr. Max Mustermann', $event->getContents()['text/html']);
            $phpunit->assertStringContainsString($dataBag->get('email'), $event->getContents()['text/html']);
        };

        $customerEventDidRun = false;
        $listenerCustomerEventClosure = function (CustomerRegisterEvent $event) use (&$customerEventDidRun, $phpunit, $customerRegisterData): void {
            $customerEventDidRun = true;
            $phpunit->assertSame($customerRegisterData['email'], $event->getCustomer()->getEmail());
        };

        $dispatcher->addListener(MailSentEvent::class, $listenerMailEventClosure);
        $dispatcher->addListener(CustomerRegisterEvent::class, $listenerCustomerEventClosure);

        $this->accountRegistrationService->register($dataBag, false, $salesChannelContext);

        $dispatcher->removeListener(MailSentEvent::class, $listenerMailEventClosure);
        $dispatcher->removeListener(CustomerRegisterEvent::class, $listenerCustomerEventClosure);

        static::assertTrue($mailEventDidRun, 'The mail.sent Event did not run');
        static::assertTrue($customerEventDidRun, 'The "' . CustomerRegisterEvent::class . '" Event did not run');
    }

    public function testRegisterWithSalesChannelSpecificConfig(): void
    {
        $salesChannelId = Uuid::randomHex();

        $this->createSalesChannel(['id' => $salesChannelId]);
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), $salesChannelId);

        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set('core.loginRegistration.additionalAddressField1Required', false);
        $systemConfigService->set('core.loginRegistration.showAdditionalAddressField1', false);
        $systemConfigService->set('core.loginRegistration.additionalAddressField1Required', true, $salesChannelId);
        $systemConfigService->set('core.loginRegistration.showAdditionalAddressField1', true, $salesChannelId);

        $systemConfigService->set('core.loginRegistration.additionalAddressField2Required', false);
        $systemConfigService->set('core.loginRegistration.showAdditionalAddressField2', false);
        $systemConfigService->set('core.loginRegistration.additionalAddressField2Required', true, $salesChannelId);
        $systemConfigService->set('core.loginRegistration.showAdditionalAddressField2', true, $salesChannelId);

        $customerRegisterData = new DataBag();
        $customerRegisterData->add($this->getCustomerRegisterData());
        $customerRegisterData->get('billingAddress')->remove('additionalAddressLine1');
        $customerRegisterData->get('billingAddress')->remove('additionalAddressLine2');
        $customerRegisterData->get('shippingAddress')->remove('additionalAddressLine1');
        $customerRegisterData->get('shippingAddress')->remove('additionalAddressLine2');

        $exceptionWasThrown = false;

        try {
            $this->accountRegistrationService->register($customerRegisterData, false, $salesChannelContext);
        } catch (ConstraintViolationException $e) {
            $exceptionWasThrown = true;

            static::assertEquals(4, $e->getViolations()->count());
            static::assertEquals(1, $e->getViolations('/billingAddress/additionalAddressLine1')->count());
            static::assertEquals(1, $e->getViolations('/billingAddress/additionalAddressLine2')->count());
            static::assertEquals(1, $e->getViolations('/shippingAddress/additionalAddressLine1')->count());
            static::assertEquals(1, $e->getViolations('/shippingAddress/additionalAddressLine2')->count());
        }

        static::assertTrue($exceptionWasThrown);
    }

    public function testRegisterWithDoubleOptIn(): void
    {
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set('core.loginRegistration.doubleOptInRegistration', true);

        $salesChannelContext = $this->createContextWithTestDomain();

        $this->assignMailtemplatesToSalesChannel($salesChannelContext->getSalesChannel()->getId(), $salesChannelContext->getContext());

        $customerRegisterData = $this->getCustomerRegisterData();

        $dataBag = new DataBag();
        $dataBag->add($customerRegisterData);

        /** @var CustomerDoubleOptInRegistrationEvent $event */
        $event = null;
        $this->catchEvent(CustomerDoubleOptInRegistrationEvent::class, $event);

        $this->accountRegistrationService->register($dataBag, false, $salesChannelContext);

        static::assertMailEvent(CustomerDoubleOptInRegistrationEvent::class, $event, $salesChannelContext);
        static::assertMailRecipientStructEvent($this->getMailRecipientStruct($customerRegisterData), $event);
    }

    public function testRegisterWithDoubleOptInAsGuest(): void
    {
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set('core.loginRegistration.doubleOptInGuestOrder', true);

        $salesChannelContext = $this->createContextWithTestDomain();

        $this->assignMailtemplatesToSalesChannel($salesChannelContext->getSalesChannel()->getId(), $salesChannelContext->getContext());

        $customerRegisterData = $this->getCustomerRegisterData();
        $customerRegisterData['password'] = null;
        $customerRegisterData['guest'] = true;

        $dataBag = new DataBag();
        $dataBag->add($customerRegisterData);

        /** @var DoubleOptInGuestOrderEvent $event */
        $event = null;
        $this->catchEvent(DoubleOptInGuestOrderEvent::class, $event);

        $this->accountRegistrationService->register($dataBag, true, $salesChannelContext);

        static::assertMailEvent(DoubleOptInGuestOrderEvent::class, $event, $salesChannelContext);
        static::assertMailRecipientStructEvent($this->getMailRecipientStruct($customerRegisterData), $event);
    }

    public function testFinishDoubleOptInRegistration(): void
    {
        $salesChannelContext = $this->createContextWithTestDomain();

        $this->assignMailtemplatesToSalesChannel($salesChannelContext->getSalesChannel()->getId(), $salesChannelContext->getContext());

        $email = 'test@test.com';
        $hash = Uuid::randomHex();

        $this->createDoubleOptInCustomer($salesChannelContext, $email, 'shopware', $hash);

        $customerConfirmData = [
            'em' => hash('sha1', $email),
            'hash' => $hash,
        ];

        $dataBag = new DataBag();
        $dataBag->add($customerConfirmData);

        /** @var CustomerRegisterEvent $event */
        $event = null;
        $this->catchEvent(CustomerRegisterEvent::class, $event);

        $this->accountRegistrationService->finishDoubleOptInRegistration($dataBag, $salesChannelContext);

        static::assertMailEvent(CustomerRegisterEvent::class, $event, $salesChannelContext);
        static::assertMailRecipientStructEvent($this->getMailRecipientStruct(['email' => $email, 'firstName' => 'Max', 'lastName' => 'Mustermann']), $event);
    }

    private function getMailRecipientStruct(array $customerData): MailRecipientStruct
    {
        return new MailRecipientStruct([
            $customerData['email'] => $customerData['firstName'] . ' ' . $customerData['lastName'],
        ]);
    }

    private function createDoubleOptInCustomer(
        SalesChannelContext $salesChannelContext,
        string $email,
        string $password,
        string $hash
    ): void {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $this->getContainer()->get('customer.repository')->create([
            [
                'id' => $customerId,
                'salesChannelId' => $salesChannelContext->getSalesChannel()->getId(),
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'Invoice',
                    'description' => 'Default payment method',
                    'handlerIdentifier' => SyncTestPaymentHandler::class,
                    'availabilityRule' => [
                        'id' => Uuid::randomHex(),
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'cartCartAmount',
                                'value' => [
                                    'operator' => '>=',
                                    'amount' => 0,
                                ],
                            ],
                        ],
                    ],
                ],
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => $password,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
                'active' => false,
                'hash' => $hash,
            ],
        ], $salesChannelContext->getContext());
    }

    private function createContextWithTestDomain(): SalesChannelContext
    {
        $id = Uuid::randomHex();
        $salesChannel = [
            'id' => $id,
            'name' => 'test',
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => $this->getRandomId('payment_method'),
            'shippingMethodId' => $this->getRandomId('shipping_method'),
            'countryId' => $this->getRandomId('country'),
            'navigationCategoryId' => $this->getRandomId('category'),
            'accessKey' => 'test',
            'languages' => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
            ],
            'domains' => [
                [
                    'url' => 'http://test.de',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ];

        $this->getContainer()->get('sales_channel.repository')
            ->create([$salesChannel], Context::createDefaultContext());

        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        return $salesChannelContextFactory->create(Uuid::randomHex(), $id);
    }

    private function getRandomId(string $table): string
    {
        return Uuid::fromBytesToHex(
            (string) $this->getContainer()
            ->get(Connection::class)
            ->fetchColumn('SELECT id FROM ' . $table)
        );
    }

    private function getCustomerRegisterData(): array
    {
        $personal = [
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'password' => '12345678',
            'email' => Uuid::randomHex() . '@example.com',
            'title' => 'Phd',
            'active' => true,
            'birthdayYear' => 2000,
            'birthdayMonth' => 1,
            'birthdayDay' => 22,
            'storefrontUrl' => 'http://localhost',
            'billingAddress' => new DataBag([
                'countryId' => $this->getValidCountryId(),
                'street' => 'Examplestreet 11',
                'zipcode' => '48441',
                'city' => 'Cologne',
                'phoneNumber' => '0123456789',
                'vatId' => 'DE999999999',
                'additionalAddressLine1' => 'Additional address line 1',
                'additionalAddressLine2' => 'Additional address line 2',
            ]),
            'shippingAddress' => new DataBag([
                'countryId' => $this->getValidCountryId(),
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Test 2',
                'lastName' => 'Example 2',
                'street' => 'Examplestreet 111',
                'zipcode' => '12341',
                'city' => 'Berlin',
                'phoneNumber' => '987654321',
                'additionalAddressLine1' => 'Additional address line 01',
                'additionalAddressLine2' => 'Additional address line 02',
            ]),
        ];

        return $personal;
    }
}
