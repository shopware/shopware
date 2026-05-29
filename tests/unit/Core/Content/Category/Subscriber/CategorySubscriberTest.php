<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationCollection;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationEntity;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\CategoryEvents;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryDefinition;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryUrlGenerator;
use Shopware\Core\Content\Category\Subscriber\CategorySubscriber;
use Shopware\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(CategorySubscriber::class)]
class CategorySubscriberTest extends TestCase
{
    private const LANGUAGE_IDS = [
        'en' => Defaults::LANGUAGE_SYSTEM,
        'de' => '20354d7ae4fe47af8ff6187bc0dedede',
        'at' => '20354d7ae4fe47af8ff6187bc0aaaaaa',
    ];

    private IdsCollection $ids;

    private CategoryDefinition $definition;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        new StaticDefinitionInstanceRegistry(
            [$this->definition = new CategoryDefinition()],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
    }

    public function testHasEvents(): void
    {
        $expectedEvents = [
            CategoryEvents::CATEGORY_LOADED_EVENT => 'categoryLoaded',
            'sales_channel.category.loaded' => 'salesChannelCategoryLoaded',
            EntityWriteEvent::class => 'beforeWriteCategory',
        ];

        static::assertSame($expectedEvents, CategorySubscriber::getSubscribedEvents());
    }

    /**
     * @return iterable<array{
     *      languageCodeChain: non-empty-list<string>,
     *      expected: string
     *  }>
     */
    public static function categoryCmsSlotMergeDataProvider(): iterable
    {
        yield 'EN config' => [
            'languageCodeChain' => ['en'],
            'expected' => 'en config',
        ];

        yield 'EN/DE config' => [
            'languageCodeChain' => ['en', 'de'],
            'expected' => 'de config',
        ];

        yield 'EN/AT config' => [
            'languageCodeChain' => ['en', 'at'],
            'expected' => 'at config',
        ];

        yield 'EN/DE/AT config' => [
            'languageCodeChain' => ['en', 'de', 'at'],
            'expected' => 'at config',
        ];
    }

    /**
     * @param non-empty-list<string> $languageCodeChain
     */
    #[DataProvider('categoryCmsSlotMergeDataProvider')]
    public function testCategoryCmsSlotMergeOnCategoryLoaded(array $languageCodeChain, string $expected): void
    {
        $category = new CategoryEntity();
        $category->setId($this->ids->create('category'));
        $category->setCmsPageId($this->ids->create('cms-page'));
        $category->setType(CategoryDefinition::TYPE_PAGE);
        $category->addTranslated('slotConfig', [
            'content' => [
                'value' => 'en config',
            ],
        ]);

        $categoryTranslations = array_map(static function (string $languageCode) {
            $translation = new CategoryTranslationEntity();
            $translation->setUniqueIdentifier('category-translation-' . $languageCode);
            $translation->setLanguageId(self::LANGUAGE_IDS[$languageCode]);
            $translation->setSlotConfig([
                'content' => [
                    'value' => $languageCode . ' config',
                ],
            ]);

            return $translation;
        }, $languageCodeChain);

        $category->setTranslations(new CategoryTranslationCollection($categoryTranslations));

        $context = Context::createDefaultContext();
        $context->assign([
            'languageIdChain' => array_map(static fn (string $code) => self::LANGUAGE_IDS[$code], array_reverse($languageCodeChain)),
        ]);
        $event = new EntityLoadedEvent(new CategoryDefinition(), [$category], $context);

        $subscriber = $this->createSubscriber(null, languageChain: $languageCodeChain);

        $subscriber->categoryLoaded($event);

        static::assertSame([
            'content' => [
                'value' => $expected,
            ],
        ], $category->getSlotConfig());
    }

    public function testSalesChannelCategoryLoadedEvent(
    ): void {
        $systemConfigService = self::getSystemConfigServiceMock();

        $categoryUrlGenerator = $this->createMock(CategoryUrlGenerator::class);
        $categoryUrlGenerator->method('generate')->willReturn('https://example.com');

        $categorySubscriber = new CategorySubscriber(
            $systemConfigService,
            $categoryUrlGenerator,
            $this->createConnectionMock(),
            new EntityCmsSlotConfigInheritanceBuilder(
                $this->createConnectionWithParentLanguageIds(['en']),
                new ArrayAdapter()
            ),
        );

        $category = new SalesChannelCategoryEntity();
        $category->setId($this->ids->getBytes('category'));

        $event = new SalesChannelEntityLoadedEvent(
            new SalesChannelCategoryDefinition(),
            [$category],
            Generator::generateSalesChannelContext()
        );

        $categorySubscriber->salesChannelCategoryLoaded($event);

        static::assertSame('https://example.com', $category->getSeoUrl());
    }

    public function testDoNothingIfNoCommands(): void
    {
        $subscriber = $this->createSubscriber($this->ids->get('default-cms'));
        $event = EntityWriteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [],
        );

        $subscriber->beforeWriteCategory($event);
        static::assertCount(0, $event->getCommandsForEntity(CategoryDefinition::ENTITY_NAME));
    }

    public function testInsertWithoutCmsPageIdAddsDefault(): void
    {
        $defaultCmsPageId = $this->ids->get('default-cms');

        $subscriber = $this->createSubscriber($defaultCmsPageId);

        $command = new InsertCommand(
            $this->definition,
            ['name' => 'Test Category'],
            ['id' => $this->ids->getBytes('category')],
            $this->createMock(EntityExistence::class),
            '/0'
        );

        $this->dispatchEvent($subscriber, [$command]);

        static::assertSame(Uuid::fromHexToBytes($defaultCmsPageId), $command->getPayload()['cms_page_id']);
    }

    public function testInsertWithNullCmsPageIdAddsDefault(): void
    {
        $defaultCmsPageId = $this->ids->get('default-cms');

        $subscriber = $this->createSubscriber($defaultCmsPageId);

        $command = new InsertCommand(
            $this->definition,
            ['cms_page_id' => null],
            ['id' => $this->ids->getBytes('category')],
            $this->createMock(EntityExistence::class),
            '/0'
        );

        $this->dispatchEvent($subscriber, [$command]);

        static::assertSame(Uuid::fromHexToBytes($defaultCmsPageId), $command->getPayload()['cms_page_id']);
    }

    public function testInsertWithExplicitCmsPageIdKeepsIt(): void
    {
        $defaultCmsPageId = $this->ids->get('default-cms');
        $explicitCmsPageId = $this->ids->getBytes('explicit-cms');

        $subscriber = $this->createSubscriber($defaultCmsPageId);

        $command = new InsertCommand(
            $this->definition,
            ['cms_page_id' => $explicitCmsPageId],
            ['id' => $this->ids->getBytes('category')],
            $this->createMock(EntityExistence::class),
            '/0'
        );

        $this->dispatchEvent($subscriber, [$command]);

        static::assertSame($explicitCmsPageId, $command->getPayload()['cms_page_id']);
    }

    public function testUpdateWithNullCmsPageIdSetsDefault(): void
    {
        $defaultCmsPageId = $this->ids->get('default-cms');

        $subscriber = $this->createSubscriber($defaultCmsPageId);

        $command = new UpdateCommand(
            $this->definition,
            ['cms_page_id' => null],
            ['id' => $this->ids->getBytes('category')],
            $this->createMock(EntityExistence::class),
            '/0'
        );

        $this->dispatchEvent($subscriber, [$command]);

        static::assertSame(Uuid::fromHexToBytes($defaultCmsPageId), $command->getPayload()['cms_page_id']);
    }

    public function testUpdateWithExplicitCmsPageIdKeepsIt(): void
    {
        $defaultCmsPageId = $this->ids->get('default-cms');
        $explicitCmsPageId = $this->ids->getBytes('explicit-cms');

        $subscriber = $this->createSubscriber($defaultCmsPageId);

        $command = new UpdateCommand(
            $this->definition,
            ['cms_page_id' => $explicitCmsPageId],
            ['id' => $this->ids->getBytes('category')],
            $this->createMock(EntityExistence::class),
            '/0'
        );

        $this->dispatchEvent($subscriber, [$command]);

        static::assertSame($explicitCmsPageId, $command->getPayload()['cms_page_id']);
    }

    public function testUpdateWithoutCmsPageIdInPayloadDoesNotModify(): void
    {
        $defaultCmsPageId = $this->ids->get('default-cms');

        $subscriber = $this->createSubscriber($defaultCmsPageId);

        $command = new UpdateCommand(
            $this->definition,
            ['name' => 'Updated Name'],
            ['id' => $this->ids->getBytes('category')],
            $this->createMock(EntityExistence::class),
            '/0'
        );

        $this->dispatchEvent($subscriber, [$command]);

        static::assertArrayNotHasKey('cms_page_id', $command->getPayload());
    }

    public function testDeleteCommandIsSkipped(): void
    {
        $defaultCmsPageId = $this->ids->get('default-cms');

        $subscriber = $this->createSubscriber($defaultCmsPageId);

        $command = new DeleteCommand(
            $this->definition,
            ['id' => $this->ids->getBytes('category')],
            $this->createMock(EntityExistence::class),
        );

        $this->dispatchEvent($subscriber, [$command]);

        static::assertArrayNotHasKey('cms_page_id', $command->getPayload());
    }

    public function testSkipsWhenNoDefaultConfigured(): void
    {
        $subscriber = $this->createSubscriber(null);

        $command = new InsertCommand(
            $this->definition,
            ['name' => 'Test Category'],
            ['id' => $this->ids->getBytes('category')],
            $this->createMock(EntityExistence::class),
            '/0'
        );

        $this->dispatchEvent($subscriber, [$command]);

        static::assertArrayNotHasKey('cms_page_id', $command->getPayload());
    }

    public function testSkipsWhenConfiguredDefaultCmsPageIdIsInvalid(): void
    {
        $subscriber = $this->createSubscriber('invalid-id');

        $command = new InsertCommand(
            $this->definition,
            ['name' => 'Test Category'],
            ['id' => $this->ids->getBytes('category')],
            $this->createMock(EntityExistence::class),
            '/0'
        );

        $this->dispatchEvent($subscriber, [$command]);

        static::assertArrayNotHasKey('cms_page_id', $command->getPayload());
    }

    public function testSkipsWhenConfiguredDefaultCmsPageDoesNotExist(): void
    {
        $subscriber = $this->createSubscriber($this->ids->get('missing-default-cms'), false);

        $command = new InsertCommand(
            $this->definition,
            ['name' => 'Test Category'],
            ['id' => $this->ids->getBytes('category')],
            $this->createMock(EntityExistence::class),
            '/0'
        );

        $this->dispatchEvent($subscriber, [$command]);

        static::assertArrayNotHasKey('cms_page_id', $command->getPayload());
    }

    public function testMultipleCommandsProcessedCorrectly(): void
    {
        $defaultCmsPageId = $this->ids->get('default-cms');
        $explicitCmsPageId = $this->ids->getBytes('explicit-cms');

        $subscriber = $this->createSubscriber($defaultCmsPageId);

        $insertWithout = new InsertCommand(
            $this->definition,
            ['name' => 'No CMS'],
            ['id' => $this->ids->getBytes('cat-1')],
            $this->createMock(EntityExistence::class),
            '/0'
        );

        $insertWith = new InsertCommand(
            $this->definition,
            ['cms_page_id' => $explicitCmsPageId],
            ['id' => $this->ids->getBytes('cat-2')],
            $this->createMock(EntityExistence::class),
            '/1'
        );

        $updateNull = new UpdateCommand(
            $this->definition,
            ['cms_page_id' => null],
            ['id' => $this->ids->getBytes('cat-3')],
            $this->createMock(EntityExistence::class),
            '/2'
        );

        $updateUnrelated = new UpdateCommand(
            $this->definition,
            ['name' => 'Renamed'],
            ['id' => $this->ids->getBytes('cat-4')],
            $this->createMock(EntityExistence::class),
            '/3'
        );

        $this->dispatchEvent($subscriber, [$insertWithout, $insertWith, $updateNull, $updateUnrelated]);

        $defaultBytes = Uuid::fromHexToBytes($defaultCmsPageId);

        static::assertSame($defaultBytes, $insertWithout->getPayload()['cms_page_id']);
        static::assertSame($explicitCmsPageId, $insertWith->getPayload()['cms_page_id']);
        static::assertSame($defaultBytes, $updateNull->getPayload()['cms_page_id']);
        static::assertArrayNotHasKey('cms_page_id', $updateUnrelated->getPayload());
    }

    private static function getSystemConfigServiceMock(?string $cmsPageId = null): SystemConfigService
    {
        if ($cmsPageId === null) {
            return new StaticSystemConfigService([]);
        }

        return new StaticSystemConfigService([
            CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY => $cmsPageId,
        ]);
    }

    /**
     * @param list<string> $languageChain
     */
    private function createSubscriber(?string $defaultCmsPageId, bool $defaultCmsPageExists = true, array $languageChain = ['en']): CategorySubscriber
    {
        $config = $defaultCmsPageId !== null
            ? [CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY => $defaultCmsPageId]
            : [];

        return new CategorySubscriber(
            new StaticSystemConfigService($config),
            $this->createMock(CategoryUrlGenerator::class),
            $this->createConnectionMock($defaultCmsPageExists),
            new EntityCmsSlotConfigInheritanceBuilder(
                $this->createConnectionWithParentLanguageIds($languageChain),
                new ArrayAdapter()
            ),
        );
    }

    private function createConnectionMock(bool $defaultCmsPageExists = true): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn($defaultCmsPageExists ? Uuid::fromHexToBytes($this->ids->get('default-cms')) : false);

        return $connection;
    }

    /**
     * @param array<WriteCommand> $commands
     */
    private function dispatchEvent(CategorySubscriber $subscriber, array $commands): void
    {
        $event = EntityWriteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            $commands,
        );

        $subscriber->beforeWriteCategory($event);
    }

    /**
     * @param non-empty-list<string> $languageCodeChain
     */
    private function createConnectionWithParentLanguageIds(array $languageCodeChain): Connection
    {
        $connection = $this->createMock(Connection::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();

        $parentLanguageIds = [];
        for ($i = \count($languageCodeChain) - 1; $i > 0; --$i) {
            $parentLanguageIds[] = self::LANGUAGE_IDS[$languageCodeChain[$i - 1]];
        }
        $parentLanguageIds[] = null;

        $results = array_map(function (?string $parentLanguageId): Result {
            $result = $this->createMock(Result::class);
            $result->method('fetchOne')->willReturn($parentLanguageId);

            return $result;
        }, $parentLanguageIds);

        $queryBuilder->method('executeQuery')->willReturnOnConsecutiveCalls(...$results);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        return $connection;
    }
}
