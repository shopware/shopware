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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ResetPasswordRoute::class)]
class ResetPasswordRouteTest extends TestCase
{
    public function testResetsNormalizedCounters(): void
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setEmail(" \u{00A0}User@Example.COM\u{3000}  ");
        $recovery = new CustomerRecoveryEntity();
        $recovery->setId(Uuid::randomHex());
        $recovery->setUniqueIdentifier($recovery->getId());
        $recovery->setCustomer($customer);
        $recovery->setCreatedAt(new \DateTimeImmutable());
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult('customer_recovery', 1, new CustomerRecoveryCollection([$recovery]), null, new Criteria(), Context::createDefaultContext()));
        $requestStack = new RequestStack();
        $requestStack->push(new Request(server: ['REMOTE_ADDR' => '10.0.0.1']));
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->exactly(2))->method('reset')->willReturnCallback(static function (string $route, string $key): void {
            static::assertContains($route, [RateLimiter::LOGIN_ROUTE, RateLimiter::RESET_PASSWORD]);
            static::assertSame('user@example.com-10.0.0.1', $key);
        });
        $route = new ResetPasswordRoute(
            $this->createMock(EntityRepository::class),
            $repository,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(DataValidator::class),
            $this->createMock(SystemConfigService::class),
            $requestStack,
            $rateLimiter,
        );

        $route->resetPassword(new RequestDataBag(['hash' => 'hash', 'newPassword' => 'password', 'newPasswordConfirm' => 'password']), Generator::generateSalesChannelContext());
    }
}
