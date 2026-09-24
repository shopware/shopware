<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Health\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\RuleLoaderResult;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomain;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainContextFactory;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelDomainContextFactory::class)]
class SalesChannelDomainContextFactoryTest extends TestCase
{
    private IdsCollection $ids;

    private SalesChannelDomain $domain;

    private SalesChannelContext $context;

    private ?string $contextToken = null;

    /**
     * @var array<string, mixed>
     */
    private array $contextOptions = [];

    private ?string $contextSalesChannelId = null;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = Generator::generateSalesChannelContext();
        $this->domain = SalesChannelDomain::create(
            $this->ids->get('sales-channel'),
            'http://localhost:8000/en',
            $this->ids->get('domain'),
            $this->ids->get('language'),
            $this->ids->get('currency'),
        );
    }

    /**
     * Language, currency and domain are determined by the URL that is probed, so the lookup context has to
     * describe that domain, not the sales channel defaults.
     */
    public function testTheContextDescribesTheDomain(): void
    {
        $this->createFactory(static::createStub(CartRuleLoader::class))->create($this->domain);

        static::assertSame($this->ids->get('sales-channel'), $this->contextSalesChannelId);
        static::assertSame([
            SalesChannelContextService::DOMAIN_ID => $this->ids->get('domain'),
            SalesChannelContextService::LANGUAGE_ID => $this->ids->get('language'),
            SalesChannelContextService::CURRENCY_ID => $this->ids->get('currency'),
        ], $this->contextOptions);
    }

    /**
     * The probe request detects the rules of an anonymous visitor by calculating a new, empty cart, and
     * extensions restricting visibility by rules read them from the context. Without them the lookup would
     * skip every page that is only visible for a rule matching that visitor.
     */
    public function testTheRulesOfAnAnonymousVisitorAreDetectedInTheContext(): void
    {
        $ruleLoader = $this->createMock(CartRuleLoader::class);
        $ruleLoader->expects($this->once())
            ->method('loadByCart')
            ->willReturnCallback(function (SalesChannelContext $context, Cart $cart, CartBehavior $behavior, bool $isNew): RuleLoaderResult {
                static::assertSame($this->context, $context);
                static::assertSame($this->contextToken, $cart->getToken());
                static::assertCount(0, $cart->getLineItems());
                static::assertTrue($isNew, 'a new cart has to be matched against all rules');

                $context->setRuleIds([$this->ids->get('always-valid-rule')]);

                return new RuleLoaderResult($cart, new RuleCollection());
            });

        $context = $this->createFactory($ruleLoader)->create($this->domain);

        static::assertSame($this->context, $context);
        static::assertSame([$this->ids->get('always-valid-rule')], $context->getRuleIds());
    }

    private function createFactory(CartRuleLoader $ruleLoader): SalesChannelDomainContextFactory
    {
        $contextFactory = static::createStub(SalesChannelContextFactory::class);
        $contextFactory->method('create')->willReturnCallback(
            function (string $token, string $salesChannelId, array $options = []): SalesChannelContext {
                $this->contextToken = $token;
                $this->contextSalesChannelId = $salesChannelId;
                $this->contextOptions = $options;

                return $this->context;
            }
        );

        return new SalesChannelDomainContextFactory($contextFactory, $ruleLoader);
    }
}
