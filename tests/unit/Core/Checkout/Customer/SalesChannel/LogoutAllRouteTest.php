<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\SalesChannel\LogoutAllRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\LogoutRoute;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(LogoutAllRoute::class)]
class LogoutAllRouteTest extends TestCase
{
    protected LogoutRoute&MockObject $logoutRoute;

    protected SalesChannelContextPersister&MockObject $contextPersister;

    protected LogoutAllRoute $route;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logoutRoute = $this->createMock(LogoutRoute::class);
        $this->contextPersister = $this->createMock(SalesChannelContextPersister::class);

        $this->route = new LogoutAllRoute(
            $this->logoutRoute,
            $this->contextPersister,
        );
    }

    public function testRevokeAllCustomerTokensExecuted(): void
    {
        $context = Generator::generateSalesChannelContext();
        $data = new RequestDataBag();

        $this->logoutRoute->expects($this->once())
            ->method('logout')
            ->with($context, $data)
            ->willReturn(new ContextTokenResponse(SalesChannelContextService::getNewToken()));

        $this->contextPersister->expects($this->once())
            ->method('revokeAllCustomerTokens')
            ->with($context->getCustomerId());

        $response = $this->route->logout($context, new RequestDataBag());

        static::assertNotSame($response->getToken(), $context->getToken());
    }
}
