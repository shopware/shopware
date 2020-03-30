<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\EntitySerializer;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class EntitySerializerTest extends TestCase
{
    use KernelTestBehaviour;

    public function testSupportsAll(): void
    {
        $serializer = new EntitySerializer();

        /** @var DefinitionInstanceRegistry $definitionRegistry */
        $definitionRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);
        foreach ($definitionRegistry->getDefinitions() as $definition) {
            $entity = $definition->getEntityName();
            static::assertTrue(
                $serializer->supports($definition->getEntityName()),
                EntitySerializer::class . ' should support ' . $entity
            );
        }
    }
}
