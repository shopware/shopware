<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Commands;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Command\RefreshIndexCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class RefreshIndexCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    private RefreshIndexCommand $refreshIndexCommand;

    protected function setUp(): void
    {
        $this->refreshIndexCommand = $this->getContainer()->get(RefreshIndexCommand::class);
    }

    public function testExecuteWithSkipIndexerOption(): void
    {
        $commandTester = new CommandTester($this->refreshIndexCommand);
        $commandTester->execute([]);

        $message = $commandTester->getDisplay();

        static::assertStringContainsString('sales_channel.indexer', $message);
        static::assertStringContainsString('category.indexer', $message);

        $commandTester = new CommandTester($this->refreshIndexCommand);
        $commandTester->execute(['--skip' => 'sales_channel.indexer,category.indexer']);

        $message = $commandTester->getDisplay();

        static::assertStringNotContainsString('sales_channel.indexer', $message);
        static::assertStringNotContainsString('category.indexer', $message);

        $commandTester = new CommandTester($this->refreshIndexCommand);
        $commandTester->execute(['--only' => 'sales_channel.indexer']);

        $message = $commandTester->getDisplay();

        static::assertStringContainsString('sales_channel.indexer', $message);
        static::assertStringNotContainsString('category.indexer', $message);
    }

    public function testExecuteWithSkipSeoUpdaterOption(): void
    {
        if (!$this->getContainer()->has(NavigationPageSeoUrlRoute::class)) {
            static::markTestSkipped('SeoUrl tests need storefront bundle to be installed');
        }
        $repo = $this->getContainer()->get('seo_url.repository');
        $context = Context::createDefaultContext();
        $skip = 'sales_channel.indexer,customer.indexer,landing_page.indexer,payment_method.indexer,media.indexer,media_folder_configuration.indexer';
        $categoryA = $this->createCategoryWithoutSeoUrl();

        $commandTester = new CommandTester($this->refreshIndexCommand);
        $commandTester->execute(['--skip' => $skip]);

        $seoUrl = $repo->search(
            (new Criteria())->addFilter(new EqualsFilter('pathInfo', sprintf('/navigation/%s', $categoryA))),
            $context
        )->first();

        static::assertNotNull($seoUrl);

        $skip .= ',category.seo-url';
        $categoryB = $this->createCategoryWithoutSeoUrl();

        $commandTester = new CommandTester($this->refreshIndexCommand);
        $commandTester->execute(['--skip' => $skip]);

        $seoUrl = $repo->search(
            (new Criteria())->addFilter(new EqualsFilter('pathInfo', sprintf('/navigation/%s', $categoryB))),
            $context
        )->first();

        static::assertNull($seoUrl);
    }

    private function createCategoryWithoutSeoUrl(): string
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => Uuid::randomHex(),
            'parentId' => $this->getRootCategoryId(),
        ];

        $this->getContainer()->get('category.repository')->upsert([$data], Context::createDefaultContext());

        $this->getContainer()->get(Connection::class)->executeStatement(
            'DELETE FROM seo_url WHERE path_info = :pathInfo',
            ['pathInfo' => sprintf('/navigation/%s', $id)]
        );

        return $id;
    }

    private function getRootCategoryId(): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('category.parentId', null));
        $criteria->addSorting(new FieldSorting('category.createdAt', FieldSorting::ASCENDING));

        /** @var array<string> $categories */
        $categories = $this->getContainer()->get('category.repository')
            ->searchIds($criteria, Context::createDefaultContext())->getIds();

        return $categories[0];
    }
}
