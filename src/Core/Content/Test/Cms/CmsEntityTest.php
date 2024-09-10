<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Cms;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsPageTranslation\CmsPageTranslationDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlotTranslation\CmsSlotTranslationDefinition;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\CompiledFieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class CmsEntityTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @return list<array<string>>
     */
    public static function provideEntityClasses(): array
    {
        return [
            [CmsBlockDefinition::class],
            [CmsPageDefinition::class],
            [CmsSectionDefinition::class],
            [CmsSlotDefinition::class],
            [CategoryDefinition::class],
        ];
    }

    #[DataProvider('provideEntityClasses')]
    public function testCmsEntityIsVersionable(string $entityDefinitionClass): void
    {
        $definition = $this->getContainer()->get($entityDefinitionClass);
        static::assertInstanceOf(EntityDefinition::class, $definition);

        static::assertTrue($definition->getFields()->has('versionId'));
        static::assertTrue($definition->isVersionAware());
        self::assertContainsInstanceOf(VersionField::class, $definition->getFields());
    }

    #[DataProvider('provideEntityClasses')]
    public function testCmsRepositoryLoadsData(string $entityDefinitionClass): void
    {
        $definition = $this->getContainer()->get($entityDefinitionClass);
        static::assertInstanceOf(EntityDefinition::class, $definition);
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get($definition->getEntityName() . '.repository');
        $result = $repository->search(new Criteria(), Context::createDefaultContext());

        static::assertInstanceOf(EntitySearchResult::class, $result);
    }

    public function testTranslationDefinitionsAreVersionAware(): void
    {
        static::assertTrue($this->getContainer()->get(CmsPageTranslationDefinition::class)->isVersionAware());
        static::assertTrue($this->getContainer()->get(CmsSlotTranslationDefinition::class)->isVersionAware());
    }

    public function testCreatingAPageVersion(): void
    {
        /** @var EntityRepository<CmsPageCollection> $repository */
        $repository = $this->getContainer()->get('cms_page.repository');
        $context = Context::createDefaultContext();
        $fixture = $this->getCmsPageFixture();
        $initialCount = $repository->search(new Criteria(), $context)->getEntities()->count();

        $result = $repository->create($fixture, $context);
        $newCount = $repository->search(new Criteria(), $context)->getEntities()->count();
        static::assertSame($initialCount + 1, $newCount);

        static::assertEmpty($result->getErrors());

        $versionId = $repository->createVersion($fixture[0]['id'], $context, 'DRAFT');
        static::assertIsString($versionId);
        $newCount = $repository->search(new Criteria(), $context)->getEntities()->count();
        static::assertSame($initialCount + 1, $newCount);
    }

    public static function assertContainsInstanceOf(string $className, CompiledFieldCollection $collection): void
    {
        foreach ($collection as $item) {
            if ($item instanceof $className) {
                return;
            }
        }

        static::fail(\sprintf('Could not find %s in collection', $className));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getCmsPageFixture(): array
    {
        return [[
            'id' => Uuid::randomHex(),
            'name' => 'test',
            'type' => 'page',
            'locked' => 0,
            'sections' => [
                [
                    'id' => Uuid::randomHex(),
                    'position' => 1,
                    'type' => 'default',
                    'name' => 'NULL',
                    'locked' => 0,
                    'sizing_mode' => 'boxed',
                    'mobile_behavior' => 'wrap',
                    'blocks' => [
                        [
                            'id' => Uuid::randomHex(),
                            'position' => 1,
                            'section_position' => 'main',
                            'type' => 'form',
                            'name' => 'test form',
                            'locked' => 0,
                            'slots' => [[
                                'id' => Uuid::randomHex(),
                                'type' => 'form',
                                'slot' => 'content',
                            ]],
                        ],
                        [
                            'id' => Uuid::randomHex(),
                            'position' => 2,
                            'section_position' => 'main',
                            'type' => 'text',
                            'name' => 'test text',
                            'locked' => 0,
                        ],
                        [
                            'id' => Uuid::randomHex(),
                            'position' => 3,
                            'section_position' => 'main',
                            'type' => 'text',
                            'name' => 'test text',
                            'locked' => 0,
                        ],
                        [
                            'id' => Uuid::randomHex(),
                            'position' => 4,
                            'section_position' => 'main',
                            'type' => 'text',
                            'name' => 'test locked',
                            'locked' => 1,
                        ],
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'position' => 2,
                    'type' => 'default',
                    'name' => 'NULL',
                    'locked' => 0,
                    'sizing_mode' => 'boxed',
                    'mobile_behavior' => 'wrap',
                    'blocks' => [
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'position' => 3,
                    'type' => 'default',
                    'name' => 'NULL',
                    'locked' => 0,
                    'sizing_mode' => 'boxed',
                    'mobile_behavior' => 'wrap',
                    'blocks' => [
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'position' => 4,
                    'type' => 'default',
                    'name' => 'NULL',
                    'locked' => 0,
                    'sizing_mode' => 'boxed',
                    'mobile_behavior' => 'wrap',
                    'blocks' => [
                    ],
                ],
            ],
        ]];
    }
}
