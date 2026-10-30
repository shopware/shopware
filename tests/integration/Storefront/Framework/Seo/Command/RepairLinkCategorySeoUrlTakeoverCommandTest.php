<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Seo\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\Seo\StorefrontSalesChannelTestHelper;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\Command\RepairLinkCategorySeoUrlTakeoverCommand;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('inventory')]
class RepairLinkCategorySeoUrlTakeoverCommandTest extends TestCase
{
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    private Connection $connection;

    private CommandTester $commandTester;

    private string $salesChannelId;

    private string $rootId;

    protected function setUp(): void
    {
        $this->categoryRepository = static::getContainer()->get('category.repository');
        $this->connection = static::getContainer()->get(Connection::class);
        $this->commandTester = new CommandTester(new RepairLinkCategorySeoUrlTakeoverCommand(
            $this->connection,
            static::getContainer()->get(SeoUrlUpdater::class),
        ));

        $this->salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($this->salesChannelId, 'test', languageIds: [$this->getDeDeLanguageId()]);

        $this->rootId = Uuid::randomHex();
        $this->categoryRepository->create([['id' => $this->rootId, 'name' => 'root']], Context::createDefaultContext());
        $this->updateSalesChannelNavigationEntryPoint($this->salesChannelId, $this->rootId);
        $this->runWorker();
    }

    public function testListsAffectedCategoriesWithoutChangingThem(): void
    {
        $targetId = $this->createTakenOverTarget('Guide');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([]));

        static::assertStringContainsString($targetId, $this->commandTester->getDisplay());
        static::assertStringContainsString('Old-Guide/', $this->commandTester->getDisplay());
        static::assertSame(['Old-Guide/' => '1'], $this->getCanonicalSeoPaths($targetId));
    }

    public function testApplyGivesTheTargetItsSeoUrlBack(): void
    {
        $targetId = $this->createTakenOverTarget('Guide');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute(['--apply' => true]));

        static::assertSame(['Guide/' => '0'], $this->getCanonicalSeoPaths($targetId));

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([]));
        static::assertStringContainsString('No category is affected.', $this->commandTester->getDisplay());
    }

    public function testApplyOnlyRepairsTheGivenCategories(): void
    {
        $repairedId = $this->createTakenOverTarget('Guide');
        $skippedId = $this->createTakenOverTarget('Blog');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute(['--apply' => true, 'category-ids' => [$repairedId]]));

        static::assertSame(['Guide/' => '0'], $this->getCanonicalSeoPaths($repairedId));
        static::assertSame(['Old-Blog/' => '1'], $this->getCanonicalSeoPaths($skippedId));
    }

    public function testApplyRepairsLanguagesWithoutTheirOwnCategoryTranslation(): void
    {
        $targetId = $this->createTakenOverTarget('Guide');
        static::assertSame(['Old-Guide/' => '1'], $this->getCanonicalSeoPaths($targetId, $this->getDeDeLanguageId()));

        static::assertSame(Command::SUCCESS, $this->commandTester->execute(['--apply' => true]));

        static::assertSame(['Guide/' => '0'], $this->getCanonicalSeoPaths($targetId, $this->getDeDeLanguageId()));
    }

    public function testRejectsAnInvalidCategoryId(): void
    {
        static::assertSame(Command::INVALID, $this->commandTester->execute(['category-ids' => ['guide']]));
        static::assertStringContainsString('"guide" is not a valid category id.', $this->commandTester->getDisplay());
    }

    private function createTakenOverTarget(string $name): string
    {
        $targetId = $this->createCategory(['name' => 'Old ' . $name]);
        $this->categoryRepository->update([['id' => $targetId, 'name' => $name]], Context::createDefaultContext());
        $this->runWorker();
        static::assertSame([$name . '/' => '0'], $this->getCanonicalSeoPaths($targetId));

        $linkId = $this->createCategory([
            'name' => $name,
            'type' => CategoryDefinition::TYPE_LINK,
            'linkType' => CategoryDefinition::LINK_TYPE_CATEGORY,
            'internalLink' => $targetId,
        ]);

        $this->connection->executeStatement(
            'UPDATE seo_url SET foreign_key = :linkId WHERE foreign_key = :targetId AND sales_channel_id = :salesChannelId AND seo_path_info = :path',
            [
                'linkId' => Uuid::fromHexToBytes($linkId),
                'targetId' => Uuid::fromHexToBytes($targetId),
                'salesChannelId' => Uuid::fromHexToBytes($this->salesChannelId),
                'path' => $name . '/',
            ]
        );
        $this->connection->executeStatement(
            'UPDATE seo_url SET is_canonical = 1, is_modified = 1 WHERE foreign_key = :targetId AND sales_channel_id = :salesChannelId AND seo_path_info = :path',
            [
                'targetId' => Uuid::fromHexToBytes($targetId),
                'salesChannelId' => Uuid::fromHexToBytes($this->salesChannelId),
                'path' => 'Old-' . $name . '/',
            ]
        );

        return $targetId;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createCategory(array $data): string
    {
        $id = Uuid::randomHex();
        $this->categoryRepository->create([['id' => $id, 'parentId' => $this->rootId, ...$data]], Context::createDefaultContext());
        $this->runWorker();

        return $id;
    }

    /**
     * @return array<string, string> canonical SEO path => is_modified
     */
    private function getCanonicalSeoPaths(string $categoryId, string $languageId = Defaults::LANGUAGE_SYSTEM): array
    {
        /** @var array<string, string> $paths */
        $paths = $this->connection->fetchAllKeyValue(
            'SELECT seo_path_info, is_modified FROM seo_url
             WHERE foreign_key = :categoryId AND route_name = :routeName AND sales_channel_id = :salesChannelId
               AND language_id = :languageId AND is_canonical = 1 AND is_deleted = 0',
            [
                'categoryId' => Uuid::fromHexToBytes($categoryId),
                'routeName' => NavigationPageSeoUrlRoute::ROUTE_NAME,
                'salesChannelId' => Uuid::fromHexToBytes($this->salesChannelId),
                'languageId' => Uuid::fromHexToBytes($languageId),
            ]
        );

        return $paths;
    }
}
