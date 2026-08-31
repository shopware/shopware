<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\OneToManyAssociationFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\ExpectedArrayException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OneToManyAssociationFieldSerializer::class)]
class OneToManyAssociationFieldSerializerTest extends TestCase
{
    private OneToManySerializerBasketDefinition $definition;

    private OneToManyAssociationFieldSerializer $serializer;

    private WriteContext $writeContext;

    protected function setUp(): void
    {
        new StaticDefinitionInstanceRegistry(
            [
                'Basket' => $this->definition = new OneToManySerializerBasketDefinition(),
                'BasketLine' => new OneToManySerializerBasketLineDefinition(),
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $extractor = static::createStub(WriteCommandExtractor::class);
        $extractor->method('normalizeSingle')
            ->willReturnCallback(static fn (EntityDefinition $definition, array $data): array => $data);

        $this->serializer = new OneToManyAssociationFieldSerializer($extractor);
        $this->writeContext = WriteContext::createFromContext(Context::createDefaultContext());
    }

    public function testNormalizeAssignsTheParentForeignKeyToEverySubresource(): void
    {
        $basketId = Uuid::randomHex();
        $this->writeContext->set($this->definition->getEntityName(), 'id', $basketId);

        $data = $this->serializer->normalize(
            $this->linesField(),
            [
                'lines' => [
                    ['name' => 'first'],
                    ['name' => 'second'],
                ],
            ],
            $this->parameters()
        );

        static::assertSame(
            [
                ['name' => 'first', 'basketId' => $basketId],
                ['name' => 'second', 'basketId' => $basketId],
            ],
            $data['lines'],
            'Every subresource is linked to the parent, not just the first one'
        );
    }

    public function testNormalizeKeepsAnExplicitlyClearedForeignKey(): void
    {
        $basketId = Uuid::randomHex();
        $this->writeContext->set($this->definition->getEntityName(), 'id', $basketId);

        $data = $this->serializer->normalize(
            $this->linesField(),
            [
                'lines' => [
                    ['name' => 'detached', 'basketId' => null],
                    ['name' => 'attached'],
                ],
            ],
            $this->parameters()
        );

        static::assertSame(
            [
                ['name' => 'detached', 'basketId' => null],
                ['name' => 'attached', 'basketId' => $basketId],
            ],
            $data['lines'],
            'A subresource may reset its association for a none cascade delete'
        );
    }

    public function testNormalizeThrowsIfASubresourceIsNotAnArray(): void
    {
        $this->writeContext->set($this->definition->getEntityName(), 'id', Uuid::randomHex());

        $this->expectExceptionObject(new ExpectedArrayException('/lines'));

        $this->serializer->normalize($this->linesField(), ['lines' => ['not-an-array']], $this->parameters());
    }

    private function linesField(): OneToManyAssociationField
    {
        $field = $this->definition->getField('lines');
        static::assertInstanceOf(OneToManyAssociationField::class, $field);

        return $field;
    }

    private function parameters(): WriteParameterBag
    {
        return new WriteParameterBag(
            $this->definition,
            $this->writeContext,
            '',
            new WriteCommandQueue()
        );
    }
}

/**
 * @internal
 */
class OneToManySerializerBasketDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'basket';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new OneToManyAssociationField('lines', 'BasketLine', 'basket_id'),
        ]);
    }
}

/**
 * @internal
 */
class OneToManySerializerBasketLineDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'basket_line';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new FkField('basket_id', 'basketId', 'Basket'),
            (new StringField('name', 'name'))->addFlags(new Required()),
        ]);
    }
}
