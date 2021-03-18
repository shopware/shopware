<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;

class SalesChannelContextServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testTokenExpired(): void
    {
        $factory = $this->getContainer()->get(SalesChannelContextFactory::class); // $this->createMock(SalesChannelContextFactory::class);
        $persister = $this->createMock(SalesChannelContextPersister::class);

        $service = new SalesChannelContextService(
            $factory,
            $this->createMock(CartRuleLoader::class),
            $persister,
            $this->createMock(CartService::class)
        );

        $persister->method('load')->willReturn(['expired' => true]);
        $expiredToken = Uuid::randomHex();
        $context = $service->get(new SalesChannelContextServiceParameters(Defaults::SALES_CHANNEL, $expiredToken, Defaults::LANGUAGE_SYSTEM));
        static::assertNotEquals($expiredToken, $context->getToken());
    }

    public function testTokenNotExpired(): void
    {
        $factory = $this->getContainer()->get(SalesChannelContextFactory::class); // $this->createMock(SalesChannelContextFactory::class);
        $persister = $this->createMock(SalesChannelContextPersister::class);

        $service = new SalesChannelContextService(
            $factory,
            $this->createMock(CartRuleLoader::class),
            $persister,
            $this->createMock(CartService::class)
        );

        $persister->method('load')->willReturn(['expired' => false]);
        $noneExpiringToken = Uuid::randomHex();
        $context = $service->get(new SalesChannelContextServiceParameters(Defaults::SALES_CHANNEL, $noneExpiringToken, Defaults::LANGUAGE_SYSTEM));
        static::assertEquals($noneExpiringToken, $context->getToken());
    }
}
