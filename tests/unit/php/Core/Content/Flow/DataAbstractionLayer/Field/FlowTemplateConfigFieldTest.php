<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\DataAbstractionLayer\Field;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\DataAbstractionLayer\Field\FlowTemplateConfigField;
use Shopware\Core\Content\Flow\DataAbstractionLayer\FieldSerializer\FlowTemplateConfigFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\DataAbstractionLayer\Field\FlowTemplateConfigField
 */
class FlowTemplateConfigFieldTest extends TestCase
{
    private FlowTemplateConfigField $field;

    public function setUp(): void
    {
        $this->field = new FlowTemplateConfigField('config', 'config');
    }

    public function testGetSerializerWillReturnFieldSerializerInterfaceInstance(): void
    {
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry
            ->method('getSerializer')
            ->willReturn(
                new FlowTemplateConfigFieldSerializer(
                    $this->createMock(ValidatorInterface::class),
                    $registry
                )
            );

        $this->field->compile($registry);

        static::assertInstanceOf(FlowTemplateConfigFieldSerializer::class, $this->field->getSerializer());
    }
}
