<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Password\LegacyPasswordVerifier;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractSwitchDefaultAddressRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\CartRestorer;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Customer\SalesChannel\AccountService
 */
#[Package('checkout')]
class AccountServiceTest extends TestCase
{
    public function testLoginByValidCredentials(): void
    {
        $salesChannelContext = Generator::createSalesChannelContext();
        $customer = $salesChannelContext->getCustomer();
        static::assertNotNull($customer);
        $customer->setActive(true);
        $customer->setGuest(false);
        $customer->setPassword(TestDefaults::HASHED_PASSWORD);
        $customer->setEmail('foo@bar.de');
        $customer->setDoubleOptInRegistration(false);

        $customerRepository = new StaticEntityRepository([
            new EntitySearchResult(
                CustomerDefinition::ENTITY_NAME,
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ),
        ]);

        $loggedinSalesChannelContext = Generator::createSalesChannelContext();
        $cartRestorer = $this->createMock(CartRestorer::class);
        $cartRestorer->expects(static::once())
            ->method('restore')
            ->willReturn($loggedinSalesChannelContext);

        $beforeLoginEventCalled = false;
        $loginEventCalled = false;

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            CustomerBeforeLoginEvent::class,
            function (CustomerBeforeLoginEvent $event) use ($salesChannelContext, &$beforeLoginEventCalled): void {
                $beforeLoginEventCalled = true;
                static::assertSame('foo@bar.de', $event->getEmail());
                static::assertSame($salesChannelContext, $event->getSalesChannelContext());
            },
        );

        $eventDispatcher->addListener(
            CustomerLoginEvent::class,
            function (CustomerLoginEvent $event) use ($customer, $loggedinSalesChannelContext, &$loginEventCalled): void {
                $loginEventCalled = true;
                static::assertSame($customer, $event->getCustomer());
                static::assertSame($loggedinSalesChannelContext, $event->getSalesChannelContext());
                static::assertSame($loggedinSalesChannelContext->getToken(), $event->getContextToken());
            },
        );

        $accountService = new AccountService(
            $customerRepository,
            $eventDispatcher,
            $this->createMock(LegacyPasswordVerifier::class),
            $this->createMock(AbstractSwitchDefaultAddressRoute::class),
            $cartRestorer,
        );

        $token = $accountService->login('foo@bar.de', $salesChannelContext);
        static::assertSame($loggedinSalesChannelContext->getToken(), $token);
        static::assertTrue($beforeLoginEventCalled);
        static::assertTrue($loginEventCalled);
        static::assertCount(1, $customerRepository->updates);
        static::assertCount(1, $customerRepository->updates[0]);
        static::assertIsArray($customerRepository->updates[0][0]);
        static::assertCount(2, $customerRepository->updates[0][0]);
        static::assertSame($customer->getId(), $customerRepository->updates[0][0]['id']);
        static::assertInstanceOf(\DateTimeImmutable::class, $customerRepository->updates[0][0]['lastLogin']);
    }
}
