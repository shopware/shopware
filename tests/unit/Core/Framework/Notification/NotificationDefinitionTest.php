<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Notification\NotificationCollection;
use Shopware\Core\Framework\Notification\NotificationDefinition;
use Shopware\Core\Framework\Notification\NotificationEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NotificationDefinition::class)]
class NotificationDefinitionTest extends TestCase
{
    public function testEntityConfiguration(): void
    {
        $definition = $this->createDefinition();

        static::assertSame(NotificationDefinition::ENTITY_NAME, $definition->getEntityName());
        static::assertSame(NotificationEntity::class, $definition->getEntityClass());
        static::assertSame(NotificationCollection::class, $definition->getCollectionClass());
        static::assertSame('6.4.7.0', $definition->since());
        static::assertNotSame([], $definition->getDefaults());
    }

    public function testFieldsAreDefined(): void
    {
        static::assertNotNull($this->createDefinition()->getFields()->get('id'));
    }

    private function createDefinition(): NotificationDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [NotificationDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(NotificationDefinition::ENTITY_NAME);
        static::assertInstanceOf(NotificationDefinition::class, $definition);

        return $definition;
    }
}
