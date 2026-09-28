<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\Schema\DynamicEntityDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DynamicEntityDefinition::class)]
class DynamicEntityDefinitionTest extends TestCase
{
    public function testCreateExposesNameAndFlags(): void
    {
        $flags = [new CascadeDelete()];

        $definition = DynamicEntityDefinition::create('custom_entity_blog', [], $flags, new ContainerBuilder());

        static::assertSame('custom_entity_blog', $definition->getEntityName());
        static::assertSame($flags, $definition->getFlags());
    }

    public function testGetDefaultsCollectsOnlyFieldsWithADefault(): void
    {
        $definition = DynamicEntityDefinition::create('custom_entity_blog', [
            ['name' => 'position', 'type' => 'int', 'reference' => '', 'onDelete' => '', 'default' => 1],
            ['name' => 'rating', 'type' => 'float', 'reference' => '', 'onDelete' => ''],
            ['name' => 'payload', 'type' => 'json', 'reference' => '', 'onDelete' => '', 'default' => ['top' => true]],
        ], [], new ContainerBuilder());

        static::assertSame(
            ['position' => 1, 'payload' => ['top' => true]],
            $definition->getDefaults()
        );
    }
}
