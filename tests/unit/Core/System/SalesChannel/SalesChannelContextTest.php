<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelContext::class)]
class SalesChannelContextTest extends TestCase
{
    public function testGetRuleIdsByAreas(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $idA = Uuid::randomHex();
        $idB = Uuid::randomHex();
        $idC = Uuid::randomHex();
        $idD = Uuid::randomHex();

        $areaRuleIds = [
            'a' => [$idA, $idB],
            'b' => [$idA, $idC, $idD],
            'c' => [$idB],
            'd' => [$idC],
        ];

        $salesChannelContext->setAreaRuleIds($areaRuleIds);

        static::assertSame($areaRuleIds, $salesChannelContext->getAreaRuleIds());

        static::assertSame([$idA, $idB], $salesChannelContext->getRuleIdsByAreas(['a']));
        static::assertSame([$idA, $idB, $idC, $idD], $salesChannelContext->getRuleIdsByAreas(['a', 'b']));
        static::assertSame([$idA, $idB], $salesChannelContext->getRuleIdsByAreas(['a', 'c']));
        static::assertSame([$idC], $salesChannelContext->getRuleIdsByAreas(['d']));
        static::assertSame([], $salesChannelContext->getRuleIdsByAreas(['f']));
    }

    public function testWithPermissions(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        static::assertEmpty($salesChannelContext->getPermissions());

        $called = false;
        $salesChannelContext->withPermissions(
            [AbstractCartPersister::PERSIST_CART_ERROR_PERMISSION => true],
            function (SalesChannelContext $context) use (&$called): void {
                $called = true;

                static::assertTrue($context->hasPermission(AbstractCartPersister::PERSIST_CART_ERROR_PERMISSION));
            },
        );

        static::assertTrue($called);
        static::assertEmpty($salesChannelContext->getPermissions());
    }

    public function testWithPermissionsWithLockedPermissions(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->lockPermissions();
        static::assertEmpty($salesChannelContext->getPermissions());

        $called = false;
        $salesChannelContext->withPermissions(
            [AbstractCartPersister::PERSIST_CART_ERROR_PERMISSION => true],
            function (SalesChannelContext $context) use (&$called): void {
                $called = true;

                static::assertEmpty($context->getPermissions());
            },
        );

        static::assertTrue($called);
        static::assertEmpty($salesChannelContext->getPermissions());
    }
}
