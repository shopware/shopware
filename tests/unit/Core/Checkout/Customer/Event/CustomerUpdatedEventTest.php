<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerUpdatedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerUpdatedEvent::class)]
class CustomerUpdatedEventTest extends TestCase
{
    public function testWebhookPayloadContract(): void
    {
        static::assertSame([
            'customer' => ['type' => 'entity', 'entityClass' => CustomerDefinition::class, 'entityName' => CustomerDefinition::ENTITY_NAME],
            'customerId' => ['type' => 'string'],
            'changedFields' => ['type' => 'array', 'of' => ['type' => 'string']],
        ], CustomerUpdatedEvent::getAvailableData()->toArray());
    }

    public function testChangedFieldsAreExposedAsDeltaHint(): void
    {
        $customerId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $event = new CustomerUpdatedEvent(
            $context,
            $customerId,
            static fn (): CustomerEntity => new CustomerEntity(),
            ['email', 'firstName']
        );

        static::assertSame(CustomerUpdatedEvent::EVENT_NAME, $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame($customerId, $event->getCustomerId());
        static::assertSame(['email', 'firstName'], $event->getChangedFields());
        static::assertSame([
            'customerId' => $customerId,
            'changedFields' => ['email', 'firstName'],
        ], $event->getValues());
    }
}
