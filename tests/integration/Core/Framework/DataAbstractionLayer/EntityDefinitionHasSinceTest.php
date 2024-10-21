<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeMappingDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class EntityDefinitionHasSinceTest extends TestCase
{
    use KernelTestBehaviour;

    public function testAllDefinitionsHasSince(): void
    {
        $service = $this->getContainer()->get(DefinitionInstanceRegistry::class);

        $definitionsWithoutSince = [];

        foreach ($service->getDefinitions() as $definition) {
            if ($definition instanceof AttributeMappingDefinition || $definition instanceof AttributeTranslationDefinition) {
                continue;
            }

            if ($definition->since() === null) {
                $definitionsWithoutSince[] = $definition->getEntityName();
            }
        }

        static::assertCount(0, $definitionsWithoutSince, \sprintf('Following definitions does not have a since version: %s', implode(',', $definitionsWithoutSince)));
    }
}
