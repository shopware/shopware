<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Attribute\State;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\State;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(State::class)]
final class StateTest extends TestCase
{
    public function testCreateField(): void
    {
        $attribute = new State(machine: 'order.state');

        $field = $attribute->createField(
            'stateId',
            'state_id',
            'order'
        );

        static::assertInstanceOf(StateMachineStateField::class, $field);
        static::assertSame('stateId', $field->getPropertyName());
        static::assertSame('state_id', $field->getStorageName());
    }

    public function testCustomColumn(): void
    {
        $attribute = new State(
            machine: 'order.state',
            column: 'custom_state_id'
        );

        $field = $attribute->createField(
            'stateId',
            'state_id',
            'order'
        );

        static::assertInstanceOf(StateMachineStateField::class, $field);
        static::assertSame('custom_state_id', $field->getStorageName());
    }

    public function testGetFieldClass(): void
    {
        $attribute = new State(machine: 'order.state');

        static::assertSame(StateMachineStateField::class, $attribute->getFieldClass());
    }

    public function testFromArray(): void
    {
        $data = [
            'machine' => 'order.state',
            'scopes' => [Context::SYSTEM_SCOPE, Context::USER_SCOPE],
            'api' => true,
            'column' => 'state_id',
            'nullable' => false,
            'type' => State::TYPE,
            'translated' => false,
        ];

        $attribute = State::fromArray($data);

        static::assertSame('order.state', $attribute->machine);
        static::assertSame([Context::SYSTEM_SCOPE, Context::USER_SCOPE], $attribute->scopes);
        static::assertTrue($attribute->api);
        static::assertSame('state_id', $attribute->column);
        static::assertFalse($attribute->nullable);
    }

    public function testDefaultScopes(): void
    {
        $attribute = new State(machine: 'order.state');

        static::assertSame([Context::SYSTEM_SCOPE], $attribute->scopes);
    }

    public function testToDefinition(): void
    {
        $attribute = new State(
            machine: 'order.state',
            scopes: [Context::SYSTEM_SCOPE, Context::USER_SCOPE],
            api: true,
            column: 'custom_state_id'
        );
        $attribute->nullable = false;

        $definition = $attribute->toDefinition();

        static::assertSame([State::class, 'fromArray'], $definition->getFactory());

        $args = $definition->getArguments();
        static::assertCount(1, $args);
        static::assertIsArray($args[0]);
        static::assertSame('order.state', $args[0]['machine']);
        static::assertSame([Context::SYSTEM_SCOPE, Context::USER_SCOPE], $args[0]['scopes']);
        static::assertTrue($args[0]['api']);
        static::assertSame('custom_state_id', $args[0]['column']);
        static::assertFalse($args[0]['nullable']);
    }
}
