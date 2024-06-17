<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\EntitySchemaGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\Api\ApiDefinition\EntityDefinition\SimpleDefinition;

/**
 * @internal
 */
final class EntitySchemaGeneratorTest extends TestCase
{
    use KernelTestBehaviour;

    public function testAllEntriesHaveProtectionHints(): void
    {
        $definitionRegistry = new DefinitionInstanceRegistry(
            self::getContainer(),
            ['simple' => SimpleDefinition::class],
            ['simple' => 'simple.repository']
        );
        $definitionRegistry->register(new SimpleDefinition(), 'simple');

        $definitions = (new EntitySchemaGenerator())->getSchema($definitionRegistry->getDefinitions());

        static::assertNotEmpty($definitions);

        foreach ($definitions as $definition) {
            static::assertArrayHasKey('write-protected', $definition);
            static::assertArrayHasKey('read-protected', $definition);
        }
    }

    public function testNoEntriesHaveBothProtectionHintsTrue(): void
    {
        $definitionRegistry = new DefinitionInstanceRegistry(
            $this->getContainer(),
            ['simple' => SimpleDefinition::class],
            ['simple' => 'simple.repository']
        );
        $definitionRegistry->register(new SimpleDefinition(), 'simple');

        $definitions = (new EntitySchemaGenerator())->getSchema($definitionRegistry->getDefinitions());

        foreach ($definitions as $definition) {
            static::assertFalse($definition['write-protected'] && $definition['read-protected']);
        }
    }
}
