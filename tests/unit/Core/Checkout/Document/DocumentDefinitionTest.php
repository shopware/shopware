<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentDefinition::class)]
class DocumentDefinitionTest extends TestCase
{
    public function testOrderIsTheParentDefinition(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [DocumentDefinition::class, OrderDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(DocumentDefinition::ENTITY_NAME);

        static::assertInstanceOf(OrderDefinition::class, $definition->getParentDefinition());
    }
}
