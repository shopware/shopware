<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaDefinition::class)]
class MediaDefinitionTest extends TestCase
{
    private MediaDefinition $definition;

    protected function setUp(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [
                MediaDefinition::class,
                ProductDocumentDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );

        $definition = $registry->getByEntityName(MediaDefinition::ENTITY_NAME);
        static::assertInstanceOf(MediaDefinition::class, $definition);

        $this->definition = $definition;
    }

    public function testProductDocumentsAssociationIsWiredToProductDocumentDefinition(): void
    {
        $field = $this->definition->getFields()->get('productDocuments');

        static::assertInstanceOf(OneToManyAssociationField::class, $field);
        static::assertSame(ProductDocumentDefinition::class, $field->getReferenceClass());
        static::assertSame('media_id', $field->getReferenceField());
        static::assertSame('id', $field->getLocalField());
    }

    public function testReferencedProductDocumentsRestrictMediaDeletion(): void
    {
        $field = $this->definition->getFields()->get('productDocuments');

        static::assertInstanceOf(OneToManyAssociationField::class, $field);
        static::assertTrue($field->is(RestrictDelete::class));
    }
}
