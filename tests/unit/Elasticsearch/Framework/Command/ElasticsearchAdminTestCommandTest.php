<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Admin\AdminSearcher;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchAdminTestCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ElasticsearchAdminTestCommand::class)]
class ElasticsearchAdminTestCommandTest extends TestCase
{
    #[TestDox('The search result totals are printed per admin index')]
    public function testPrintsSearchResultPerIndex(): void
    {
        $searcher = $this->createMock(AdminSearcher::class);
        $searcher
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (string $term, array $entities): array {
                $this->assertSame('shopware', $term);
                $this->assertContains(ProductDefinition::ENTITY_NAME, $entities);

                return [
                    'product' => [
                        'total' => 5,
                        'data' => [],
                        'indexer' => 'product-listing',
                        'index' => 'sw-admin-product',
                    ],
                ];
            });

        $commandTester = new CommandTester(new ElasticsearchAdminTestCommand($searcher));

        static::assertSame(Command::SUCCESS, $commandTester->execute(['term' => 'shopware']));

        $display = $commandTester->getDisplay();
        static::assertStringContainsString('sw-admin-product', $display);
        static::assertStringContainsString('product-listing', $display);
        static::assertStringContainsString('5', $display);
    }
}
