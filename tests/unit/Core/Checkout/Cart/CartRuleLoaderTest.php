<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\CartFactory;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\RuleLoader;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Cart\CartRuleLoader
 */
class CartRuleLoaderTest extends TestCase
{
    public function testLoadByTokenCreatesNewCart(): void
    {
        $newCart = new Cart('test');
        $factory = $this->createMock(CartFactory::class);
        $factory
            ->expects(static::once())
            ->method('createNew')
            ->with('test')
            ->willReturn($newCart);

        $persister = $this->createMock(AbstractCartPersister::class);
        $persister
            ->expects(static::once())
            ->method('load')
            ->with('test')
            ->willThrowException(CartException::tokenNotFound('test'));

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects(static::once())
            ->method('getToken')
            ->willReturn('test');
        $salesChannelContext
            ->expects(static::exactly(2))
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

        $calculatedCart = new Cart('calculated');
        $processor = $this->createMock(Processor::class);
        $processor
            ->expects(static::exactly(3))
            ->method('process')
            ->with(static::isInstanceOf(Cart::class), $salesChannelContext, static::isInstanceOf(CartBehavior::class))
            ->willReturn($calculatedCart);

        $ruleLoader = $this->createMock(RuleLoader::class);
        $ruleLoader
            ->expects(static::once())
            ->method('load')
            ->with($salesChannelContext->getContext())
            ->willReturn(new RuleCollection());

        $cartRuleLoader = new CartRuleLoader(
            $persister,
            $processor,
            new NullLogger(),
            $this->createMock(CacheInterface::class),
            $ruleLoader,
            $this->createMock(TaxDetector::class),
            $this->createMock(Connection::class),
            $factory,
        );

        static::assertSame($calculatedCart, $cartRuleLoader->loadByToken($salesChannelContext, $salesChannelContext->getToken())->getCart());
    }
}
