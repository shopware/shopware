<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\EventLog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogCollection;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookEventLogDefinition::class)]
class WebhookEventLogDefinitionTest extends TestCase
{
    public function testEntityConfiguration(): void
    {
        $definition = $this->createDefinition();

        static::assertSame(WebhookEventLogDefinition::ENTITY_NAME, $definition->getEntityName());
        static::assertSame(WebhookEventLogEntity::class, $definition->getEntityClass());
        static::assertSame(WebhookEventLogCollection::class, $definition->getCollectionClass());
        static::assertSame('6.4.1.0', $definition->since());
        static::assertNotSame([], $definition->getDefaults());
    }

    public function testFieldsAreDefined(): void
    {
        static::assertNotNull($this->createDefinition()->getFields()->get('id'));
    }

    private function createDefinition(): WebhookEventLogDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [WebhookEventLogDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(WebhookEventLogDefinition::ENTITY_NAME);
        static::assertInstanceOf(WebhookEventLogDefinition::class, $definition);

        return $definition;
    }
}
