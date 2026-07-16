<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Write\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(WriteCommand::class)]
class WriteCommandTest extends TestCase
{
    public function testDetectsAnyPayloadFieldIncludingNull(): void
    {
        $id = Uuid::randomBytes();
        $registry = new StaticDefinitionInstanceRegistry(
            [new ProductDefinition()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
        $command = new InsertCommand(
            $registry->get(ProductDefinition::class),
            ['id' => $id, 'active' => null],
            ['id' => $id],
            EntityExistence::createEmpty(),
            '/product',
        );

        static::assertTrue($command->hasAnyField('name', 'active'));
        static::assertFalse($command->hasAnyField('name', 'stock'));
    }
}
