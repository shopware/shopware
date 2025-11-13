<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Content\MeasurementSystem\MeasurementUnits;
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
        static::assertSame([
            'extensions' => [],
            'system' => 'metric',
            'units' => [
                'length' => 'mm',
                'weight' => 'kg',
            ],
        ], $salesChannelContext->getMeasurementSystem()->jsonSerialize());

        $newMeasurementSystem = new MeasurementUnits(
            'imperial',
            [
                'length' => 'in',
                'weight' => 'lb',
            ]
        );

        $salesChannelContext->setMeasurementSystem($newMeasurementSystem);
        static::assertSame([
            'extensions' => [],
            'system' => 'imperial',
            'units' => [
                'length' => 'in',
                'weight' => 'lb',
            ],
        ], $salesChannelContext->getMeasurementSystem()->jsonSerialize());
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

    public function testSalesChannelContextStateFunctionPassesResetsAndKeepsState(): void
    {
        $manualState = 'manual-state';
        $closureState = 'closure-state';

        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->addState($manualState);

        static::assertTrue($salesChannelContext->hasState($manualState));
        static::assertTrue($salesChannelContext->getContext()->hasState($manualState));
        static::assertFalse($salesChannelContext->hasState($closureState));
        static::assertFalse($salesChannelContext->getContext()->hasState($closureState));

        $closureStates = $salesChannelContext->state(static function (SalesChannelContext $closureContext): array {
            return $closureContext->getStates();
        }, $closureState);

        static::assertContains($closureState, $closureStates);
        static::assertContains($manualState, $closureStates);
        static::assertTrue($salesChannelContext->hasState($manualState));
        static::assertTrue($salesChannelContext->getContext()->hasState($manualState));
        static::assertFalse($salesChannelContext->hasState($closureState));
        static::assertFalse($salesChannelContext->getContext()->hasState($closureState));
    }
}
