<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\CustomEntityException;
use Shopware\Core\System\CustomEntity\Schema\DynamicFieldFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DynamicFieldFactory::class)]
class DynamicFieldFactoryTest extends TestCase
{
    private ContainerInterface&MockObject $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = $this->createMock(ContainerInterface::class);
    }

    public function testCreateThrowsAnExceptionWhenTheServiceIsNotFound(): void
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('You have requested a non-existent service "Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry".');

        $factory = new DynamicFieldFactory();

        $factory->create($this->container, 'test', [
            ['name' => 'test', 'type' => '', 'reference' => '', 'onDelete' => ''],
        ]);
    }

    public function testGetDeletedFlagThrowsAnExceptionWhenTheFieldIsUnmatched(): void
    {
        $this->expectExceptionObject(CustomEntityException::unsupportedOnDeletePropertyOnField('INVALID', 'test'));

        $factory = new DynamicFieldFactory();

        $this->container->expects($this->once())
            ->method('get')
            ->willReturn($this->createMock(DefinitionInstanceRegistry::class));

        $factory->create($this->container, 'test', [
            ['name' => 'test', 'type' => 'many-to-one', 'reference' => 'unit', 'onDelete' => 'INVALID'],
        ]);
    }
}
