<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CronIntervalField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CronIntervalFieldSerializer;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @covers \Shopware\Core\Framework\DataAbstractionLayer\Field\CronIntervalField
 *
 * @internal
 */
#[Package('checkout')]
class CronIntervalFieldTest extends TestCase
{
    private CronIntervalField $field;

    protected function setUp(): void
    {
        $this->field = new CronIntervalField('name', 'name');
    }

    public function testGetStorageName(): void
    {
        static::assertSame('name', $this->field->getStorageName());
    }

    public function testGetSerializerWillReturnFieldSerializerInterfaceInstance(): void
    {
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry
            ->method('getSerializer')
            ->willReturn(
                new CronIntervalFieldSerializer(
                    $this->createMock(ValidatorInterface::class),
                    $registry
                )
            );
        $registry->method('getResolver');
        $registry->method('getAccessorBuilder');
        $this->field->compile($registry);

        static::assertInstanceOf(CronIntervalFieldSerializer::class, $this->field->getSerializer());
    }
}
