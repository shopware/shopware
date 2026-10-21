<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Script;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\AppContextFactory;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppContextFactory::class)]
class AppContextFactoryTest extends TestCase
{
    public function testScriptWithoutAppRunsWithTheHookContext(): void
    {
        $hookContext = $this->createHookContext();

        static::assertSame($hookContext, AppContextFactory::forHookContext($hookContext)->create());
    }

    public function testAppContextCarriesTheHookValues(): void
    {
        $hookContext = $this->createHookContext();

        $context = AppContextFactory::forApp($hookContext, Uuid::randomHex(), [])->create();

        static::assertSame($hookContext->getRuleIds(), $context->getRuleIds());
        static::assertSame($hookContext->getCurrencyId(), $context->getCurrencyId());
        static::assertSame($hookContext->getLanguageIdChain(), $context->getLanguageIdChain());
        static::assertSame($hookContext->getVersionId(), $context->getVersionId());
        static::assertSame($hookContext->getCurrencyFactor(), $context->getCurrencyFactor());
        static::assertSame($hookContext->considerInheritance(), $context->considerInheritance());
        static::assertSame($hookContext->getTaxState(), $context->getTaxState());
        static::assertEquals($hookContext->getRounding(), $context->getRounding());
    }

    public function testAppContextUsesTheAppIntegrationAndPrivileges(): void
    {
        $integrationId = Uuid::randomHex();

        $source = AppContextFactory::forApp($this->createHookContext(), $integrationId, ['product:read', 'tax:read'])
            ->create()
            ->getSource();

        static::assertInstanceOf(AdminApiSource::class, $source);
        static::assertNull($source->getUserId());
        static::assertSame($integrationId, $source->getIntegrationId());
        static::assertSame(['product:read', 'tax:read'], $source->getPermissions());
        static::assertFalse($source->isAdmin());
    }

    public function testAppContextGetsItsOwnRoundingConfig(): void
    {
        $hookContext = $this->createHookContext();

        $context = AppContextFactory::forApp($hookContext, Uuid::randomHex(), [])->create();

        static::assertNotSame($hookContext->getRounding(), $context->getRounding());
    }

    public function testLaterChangesToTheHookContextDoNotReachTheAppContext(): void
    {
        $hookContext = $this->createHookContext();
        $ruleIds = $hookContext->getRuleIds();
        $factory = AppContextFactory::forApp($hookContext, Uuid::randomHex(), []);

        $hookContext->setRuleIds([Uuid::randomHex()]);
        $hookContext->setTaxState(CartPrice::TAX_STATE_FREE);
        $hookContext->getRounding()->setDecimals(4);

        $context = $factory->create();

        static::assertSame($ruleIds, $context->getRuleIds());
        static::assertSame(CartPrice::TAX_STATE_NET, $context->getTaxState());
        static::assertSame(2, $context->getRounding()->getDecimals());
    }

    private function createHookContext(): Context
    {
        return new Context(
            new SystemSource(),
            [Uuid::randomHex()],
            Uuid::randomHex(),
            [Uuid::randomHex(), Uuid::randomHex()],
            Uuid::randomHex(),
            1.5,
            true,
            CartPrice::TAX_STATE_NET,
            new CashRoundingConfig(2, 0.01, true),
        );
    }
}
