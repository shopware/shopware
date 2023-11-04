<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\LanguageSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageDefinition;

/**
 * @internal
 */
#[Package('system-settings')]
class LanguageSerializerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $languageRepository;

    private LanguageSerializer $serializer;

    private string $languageId = '1a9e90835a634ffd900b5a441251f551';

    protected function setUp(): void
    {
        $this->languageRepository = $this->getContainer()->get('language.repository');
        $serializerRegistry = $this->getContainer()->get(SerializerRegistry::class);

        $this->serializer = new LanguageSerializer($this->languageRepository);
        $this->serializer->setRegistry($serializerRegistry);
    }

    public function testSimple(): void
    {
        $this->createCountry();

        $config = new Config([], [], []);
        $language = [
            'locale' => [
                'code' => 'xx-XX',
            ],
        ];

        $serialized = iterator_to_array($this->serializer->serialize($config, $this->languageRepository->getDefinition(), $language));

        $deserialized = iterator_to_array($this->serializer->deserialize($config, $this->languageRepository->getDefinition(), $serialized));

        static::assertSame($this->languageId, $deserialized['id']);
    }

    public function testSupportsOnlyCountry(): void
    {
        $serializer = new LanguageSerializer($this->getContainer()->get('language.repository'));

        $definitionRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);
        foreach ($definitionRegistry->getDefinitions() as $definition) {
            $entity = $definition->getEntityName();

            if ($entity === LanguageDefinition::ENTITY_NAME) {
                static::assertTrue($serializer->supports($entity));
            } else {
                static::assertFalse(
                    $serializer->supports($entity),
                    LanguageDefinition::class . ' should not support ' . $entity
                );
            }
        }
    }

    private function createCountry(): void
    {
        $localeId = Uuid::randomHex();
        $this->languageRepository->upsert([
            [
                'id' => $this->languageId,
                'name' => 'test name',
                'locale' => [
                    'id' => $localeId,
                    'code' => 'xx-XX',
                    'name' => 'test name',
                    'territory' => 'test territory',
                ],
                'translationCodeId' => $localeId,
            ],
        ], Context::createDefaultContext());
    }
}
