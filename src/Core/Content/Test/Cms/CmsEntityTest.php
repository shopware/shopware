<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Cms;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsPageTranslation\CmsPageTranslationDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlotTranslation\CmsSlotTranslationDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
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

    /**
     * @dataProvider provideEntityClasses
     */
    public function testCmsEntityIsVersionable(string $entityDefinitionClass): void
    {
        /** @var EntityDefinition $definition */
        $definition = $this->getContainer()->get($entityDefinitionClass);

        static::assertTrue($definition->getFields()->has('versionId'));
        static::assertTrue($definition->isVersionAware());
        self::assertContainsInstanceOf(VersionField::class, $definition->getFields());
    }

    /**
     * @dataProvider provideEntityClasses
     */
    public function testCmsRepositoryLoadsData(string $entityDefinitionClass): void
    {
        $definition = $this->getContainer()->get($entityDefinitionClass);
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
        $repository = $this->getContainer()->get('cms_page.repository');
        $context = Context::createDefaultContext();
        $fixture = $this->getCmsPageFixture();
        $initialCount = $repository->search(new Criteria(), $context)->count();

        $result = $repository->create($fixture, $context);
        static::assertSame($initialCount + 1, $repository->search(new Criteria(), $context)->count());

        static::assertEmpty($result->getErrors());

        $versionId = $repository->createVersion($fixture[0]['id'], $context, 'DRAFT');
        static::assertIsString($versionId);
        static::assertSame($initialCount + 1, $repository->search(new Criteria(), $context)->count());
    }

    public static function assertContainsInstanceOf(string $className, iterable $collection): void
    {
        foreach ($collection as $item) {
            if ($item instanceof $className) {
                return;
            }
        }

        static::fail(sprintf('Could not find %s in collection', $className));
    }

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
