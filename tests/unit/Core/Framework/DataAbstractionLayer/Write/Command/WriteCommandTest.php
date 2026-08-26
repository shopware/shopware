<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Write\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WriteCommand::class)]
class WriteCommandTest extends TestCase
{
    public function testDetectsAnyPayloadFieldIncludingNull(): void
    {
        $command = $this->createCommand(['id' => Uuid::randomBytes(), 'active' => null]);

        static::assertTrue($command->hasAnyField('name', 'active'));
        static::assertFalse($command->hasAnyField('name', 'stock'));
        static::assertFalse($command->hasAnyField());
    }

    public function testExposesAndMutatesCommandState(): void
    {
        $id = Uuid::randomBytes();
        $existence = EntityExistence::createEmpty();
        $command = $this->createCommand(['id' => $id], $existence);

        static::assertSame('product', $command->getEntityName());
        static::assertSame(['id' => $id], $command->getPrimaryKey());
        static::assertSame(['id' => Uuid::fromBytesToHex($id)], $command->getDecodedPrimaryKey());
        static::assertSame($existence, $command->getEntityExistence());
        static::assertSame('/product', $command->getPath());
        static::assertSame(['id' => $id], $command->getPayload());
        static::assertTrue($command->isValid());
        static::assertTrue($command->hasField('id'));
        static::assertFalse($command->hasField('active'));
        static::assertFalse($command->isFailed());

        $command->addPayload('active', false);
        $command->setFailed(true);

        static::assertSame(['id' => $id, 'active' => false], $command->getPayload());
        static::assertTrue($command->hasField('active'));
        static::assertTrue($command->isFailed());
    }

    public function testCommandWithoutPayloadIsInvalid(): void
    {
        $command = $this->createCommand([]);

        static::assertFalse($command->isValid());
        static::assertFalse($command->hasAnyField('id'));
        static::assertSame([], $command->getDecodedPrimaryKey());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createCommand(array $payload, ?EntityExistence $existence = null): InsertCommand
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [new ProductDefinition()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );

        $primaryKey = isset($payload['id']) && \is_string($payload['id']) ? ['id' => $payload['id']] : [];

        return new InsertCommand(
            $registry->get(ProductDefinition::class),
            $payload,
            $primaryKey,
            $existence ?? EntityExistence::createEmpty(),
            '/product',
        );
    }
}
