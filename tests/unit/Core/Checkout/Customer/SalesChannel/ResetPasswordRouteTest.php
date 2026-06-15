<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\ResetPasswordRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ResetPasswordRoute::class)]
class ResetPasswordRouteTest extends TestCase
{
    public function testResetsAllRateLimitersOnPasswordReset(): void
    {
        $email = 'Customer@Example.com';
        $ip = '10.0.0.1';
        $hash = 'valid-hash';
        $customerId = Uuid::randomHex();
        $recoveryId = Uuid::randomHex();
        $expectedEmailKey = strtolower($email);
        $expectedCombinedKey = $expectedEmailKey . '-' . $ip;

        $customer = new CustomerEntity();
        $customer->setId($customerId);
        $customer->setEmail($email);

        $recovery = new CustomerRecoveryEntity();
        $recovery->setId($recoveryId);
        $recovery->setCustomer($customer);
        $recovery->setCreatedAt(new \DateTimeImmutable());

        $recoveryCollection = new CustomerRecoveryCollection([$recovery]);

        $customerRecoveryRepository = $this->createMock(EntityRepository::class);
        $customerRecoveryRepository->method('search')
            ->willReturn(new EntitySearchResult(
                'customer_recovery',
                1,
                $recoveryCollection,
                null,
                new Criteria(),
                $this->createMock(SalesChannelContext::class)->getContext()
            ));

        $customerRepository = $this->createMock(EntityRepository::class);

        $resetCalls = [];
        $resetIfConfiguredCalls = [];

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->exactly(2))
            ->method('reset')
            ->willReturnCallback(function (string $route, string $key) use (&$resetCalls): void {
                $resetCalls[] = [$route, $key];
            });
        $rateLimiter->expects($this->exactly(2))
            ->method('resetIfConfigured')
            ->willReturnCallback(function (string $route, string $key) use (&$resetIfConfiguredCalls): void {
                $resetIfConfiguredCalls[] = [$route, $key];
            });

        $mainRequest = new Request(server: ['REMOTE_ADDR' => $ip]);
        $requestStack = new RequestStack();
        $requestStack->push($mainRequest);

        $passwordValidationFactory = $this->createMock(DataValidationFactoryInterface::class);
        $passwordValidationFactory->method('update')->willReturn(new DataValidationDefinition());

        $route = new ResetPasswordRoute(
            $customerRepository,
            $customerRecoveryRepository,
            $this->createMock(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            $requestStack,
            $rateLimiter,
            $passwordValidationFactory,
            new NativeClock()
        );

        $context = $this->createMock(SalesChannelContext::class);
        $context->expects($this->exactly(5))->method('getContext')->willReturn(Context::createDefaultContext());

        $route->resetPassword(
            new RequestDataBag([
                'hash' => $hash,
                'newPassword' => 'newPass123!',
                'newPasswordConfirm' => 'newPass123!',
            ]),
            $context,
        );

        static::assertSame([
            [RateLimiter::LOGIN_ROUTE, $expectedCombinedKey],
            [RateLimiter::RESET_PASSWORD, $expectedCombinedKey],
        ], $resetCalls);

        static::assertSame([
            [RateLimiter::LOGIN_USER, $expectedEmailKey],
            [RateLimiter::LOGIN_CLIENT, $ip],
        ], $resetIfConfiguredCalls);
    }
}
