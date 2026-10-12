<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Aggregate\AppPaymentMethod;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodCollection;
use Shopware\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodDefinition;
use Shopware\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppPaymentMethodDefinition::class)]
class AppPaymentMethodDefinitionTest extends TestCase
{
    public function testEntityConfiguration(): void
    {
        $definition = $this->createDefinition();

        static::assertSame(AppPaymentMethodDefinition::ENTITY_NAME, $definition->getEntityName());
        static::assertSame(AppPaymentMethodEntity::class, $definition->getEntityClass());
        static::assertSame(AppPaymentMethodCollection::class, $definition->getCollectionClass());
        static::assertSame('6.4.1.0', $definition->since());
    }

    public function testFieldsAreDefined(): void
    {
        static::assertNotNull($this->createDefinition()->getFields()->get('id'));
    }

    private function createDefinition(): AppPaymentMethodDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [AppPaymentMethodDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(AppPaymentMethodDefinition::ENTITY_NAME);
        static::assertInstanceOf(AppPaymentMethodDefinition::class, $definition);

        return $definition;
    }
}
