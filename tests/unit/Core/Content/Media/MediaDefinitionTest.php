<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
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
    public function testProductDocumentsAssociation(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [
                MediaDefinition::class,
                ProductDocumentDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(MediaDefinition::ENTITY_NAME);
        $field = $definition->getFields()->get('productDocuments');

        static::assertInstanceOf(OneToManyAssociationField::class, $field);
        static::assertSame(ProductDocumentDefinition::class, $field->getReferenceClass());
        static::assertTrue($field->is(RestrictDelete::class));
    }
}
