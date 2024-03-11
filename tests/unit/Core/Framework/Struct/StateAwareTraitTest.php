<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Struct\StateAwareTrait;

/**
 * @internal
 */
#[CoversClass(StateAwareTrait::class)]
class StateAwareTraitTest extends TestCase
{
    public function testTrait(): void
    {
        $struct = new StateStruct();

        $struct->addState('foo');

        static::assertTrue($struct->hasState('foo'));

        $struct->addState('bar');

        // contains foo and bar at this point
        static::assertEquals(['foo', 'bar'], $struct->getStates(), 'States do not match');

        static::assertTrue($struct->hasState('foo'), 'foo should be set');

        static::assertTrue($struct->hasState('foo', 'bar'), 'State or check failed');

        static::assertFalse($struct->hasState('baz'), 'baz should not be set');

        $struct->removeState('foo');

        // contains only bar at this point
        static::assertEquals(['bar'], $struct->getStates(), 'States do not match');

        static::assertFalse($struct->hasState('foo'), 'foo should not be set');

        static::assertTrue($struct->hasState('bar'), 'bar should be set');

        static::assertTrue($struct->hasState('bar', 'baz'), 'State or check failed');

        static::assertFalse($struct->hasState('foo', 'baz'));

        $value = $struct->state(
            function (StateStruct $state) {
                return $state->hasState('baz');
            },
            'baz'
        );

        static::assertTrue($value, 'Baz was not added');

        static::assertEquals(['bar'], $struct->getStates(), 'States do not match');

        static::assertFalse($struct->hasState('baz'), 'baz should not be set outside');

        $value = $struct->state(
            function (StateStruct $state) {
                return $state->hasState('baz') && $state->hasState('foo');
            },
            'baz',
            'foo'
        );

        static::assertTrue($value, 'Baz or foo were not added');

        static::assertEquals(['bar'], $struct->getStates(), 'States do not match');

        $value = $struct->state(
            function (StateStruct $state) {
                return $state->state(
                    function (StateStruct $state) {
                        return $state->hasState('baz') && $state->hasState('foo');
                    },
                    'baz'
                );
            },
            'foo'
        );

        static::assertTrue($value, 'Baz or foo were not added');

        static::assertEquals(['bar'], $struct->getStates(), 'States do not match');
    }
}

/**
 * @internal
 */
class StateStruct
{
    use StateAwareTrait;
}
