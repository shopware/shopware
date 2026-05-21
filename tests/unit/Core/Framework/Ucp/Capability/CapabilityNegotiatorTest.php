<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Capability;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Capability\CapabilityNegotiator;

/**
 * @internal
 */
#[CoversClass(CapabilityNegotiator::class)]
class CapabilityNegotiatorTest extends TestCase
{
    private CapabilityNegotiator $negotiator;

    protected function setUp(): void
    {
        $this->negotiator = new CapabilityNegotiator();
    }

    public function testEmptyIntersectionIsEmpty(): void
    {
        $result = $this->negotiator->negotiate([], []);
        static::assertTrue($result->isEmpty());
    }

    public function testSingleSharedCapabilitySingleVersion(): void
    {
        $result = $this->negotiator->negotiate(
            ['dev.ucp.shopping.cart' => [['version' => '2026-01-23']]],
            ['dev.ucp.shopping.cart' => [['version' => '2026-01-23']]]
        );

        static::assertTrue($result->has('dev.ucp.shopping.cart'));
        static::assertSame([['version' => '2026-01-23']], $result->toArray()['dev.ucp.shopping.cart']);
    }

    public function testNoMutualVersionDropsCapability(): void
    {
        $result = $this->negotiator->negotiate(
            ['dev.ucp.shopping.cart' => [['version' => '2026-01-23']]],
            ['dev.ucp.shopping.cart' => [['version' => '2025-12-01']]]
        );

        static::assertTrue($result->isEmpty());
    }

    public function testHighestMutualVersionIsPicked(): void
    {
        $result = $this->negotiator->negotiate(
            ['dev.ucp.shopping.cart' => [
                ['version' => '2026-01-23'],
                ['version' => '2026-01-11'],
            ]],
            ['dev.ucp.shopping.cart' => [
                ['version' => '2026-01-23'],
                ['version' => '2026-01-11'],
            ]]
        );

        static::assertSame('2026-01-23', $result->toArray()['dev.ucp.shopping.cart'][0]['version']);
    }

    public function testExtensionWithMissingParentIsPruned(): void
    {
        $result = $this->negotiator->negotiate(
            [
                'dev.ucp.shopping.fulfillment' => [['version' => '2026-01-23', 'extends' => 'dev.ucp.shopping.checkout']],
            ],
            [
                'dev.ucp.shopping.fulfillment' => [['version' => '2026-01-23', 'extends' => 'dev.ucp.shopping.checkout']],
            ]
        );

        static::assertTrue($result->isEmpty(), 'Extension without parent in intersection should be pruned');
    }

    public function testExtensionWithPresentParentSurvives(): void
    {
        $result = $this->negotiator->negotiate(
            [
                'dev.ucp.shopping.checkout' => [['version' => '2026-01-23']],
                'dev.ucp.shopping.fulfillment' => [['version' => '2026-01-23', 'extends' => 'dev.ucp.shopping.checkout']],
            ],
            [
                'dev.ucp.shopping.checkout' => [['version' => '2026-01-23']],
                'dev.ucp.shopping.fulfillment' => [['version' => '2026-01-23', 'extends' => 'dev.ucp.shopping.checkout']],
            ]
        );

        static::assertTrue($result->has('dev.ucp.shopping.checkout'));
        static::assertTrue($result->has('dev.ucp.shopping.fulfillment'));
    }

    public function testMultiParentExtensionSurvivesIfAnyParentPresent(): void
    {
        $result = $this->negotiator->negotiate(
            [
                'dev.ucp.shopping.cart' => [['version' => '2026-01-23']],
                'dev.ucp.shopping.discount' => [['version' => '2026-01-23', 'extends' => ['dev.ucp.shopping.cart', 'dev.ucp.shopping.checkout']]],
            ],
            [
                'dev.ucp.shopping.cart' => [['version' => '2026-01-23']],
                'dev.ucp.shopping.discount' => [['version' => '2026-01-23', 'extends' => ['dev.ucp.shopping.cart', 'dev.ucp.shopping.checkout']]],
            ]
        );

        static::assertTrue($result->has('dev.ucp.shopping.discount'));
    }

    public function testTransitiveExtensionPruning(): void
    {
        // grandchild -> child -> parent
        // parent is missing -> child gets dropped -> grandchild gets dropped on next pass
        $result = $this->negotiator->negotiate(
            [
                'a.child' => [['version' => '2026-01-23', 'extends' => 'a.parent']],
                'a.grandchild' => [['version' => '2026-01-23', 'extends' => 'a.child']],
            ],
            [
                'a.child' => [['version' => '2026-01-23', 'extends' => 'a.parent']],
                'a.grandchild' => [['version' => '2026-01-23', 'extends' => 'a.child']],
            ]
        );

        static::assertTrue($result->isEmpty(), 'Transitive extensions must be pruned to fixed point');
    }

    public function testCapabilityOnlyInBusinessIsDropped(): void
    {
        $result = $this->negotiator->negotiate(
            ['dev.ucp.shopping.cart' => [['version' => '2026-01-23']]],
            []
        );

        static::assertTrue($result->isEmpty());
    }
}
