<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Command\AppListCommand;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(AppListCommand::class)]
class AppListCommandTest extends TestCase
{
    /** @var MockObject&EntityRepository<AppCollection> */
    private MockObject&EntityRepository $appRepoMock;

    private AppListCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->appRepoMock = $this->createMock(EntityRepository::class);

        $this->command = new AppListCommand($this->appRepoMock);
    }

    public function testCommand(): void
    {
        $app1 = new AppEntity();
        $app2 = new AppEntity();

        $entities = [
            $app1,
            $app2,
        ];

        $app1->setUniqueIdentifier('1');
        $app1->assign([
            'active' => true,
            'name' => 'App List Test',
            'label' => 'alt',
            'version' => '1.0.0',
            'author' => 'Shopware AG',
        ]);

        $app2->setUniqueIdentifier('2');
        $app2->assign([
            'active' => false,
            'name' => 'Inactive App',
            'label' => 'Inactive App with a very long label that will be truncated',
            'version' => '2.0.0',
            'author' => 'Test Developer',
        ]);

        $this->setupEntityCollection($entities);

        $commandTester = $this->executeCommand([]);
        static::assertSame(0, $commandTester->getStatusCode());

        $display = $commandTester->getDisplay();
        static::assertStringContainsString('Shopware App Service', $display);
        static::assertStringContainsString('App List Test', $display);
        static::assertStringContainsString('Inactive App', $display);
        static::assertStringContainsString('2 apps, 1 active', $display);
    }

    public function testFilter(): void
    {
        $filterValue = 'test-app';

        $criteria = static::callback(function (Criteria $criteria) use ($filterValue): bool {
            $filters = $criteria->getFilters();
            if (!(\count($filters) === 1 && $filters[0] instanceof MultiFilter)) {
                return false;
            }
            $filter = $filters[0];
            if ($filter->getOperator() !== MultiFilter::CONNECTION_OR) {
                return false;
            }
            $fields = ['name', 'label'];
            foreach ($filter->getQueries() as $query) {
                if (!(
                    $query instanceof ContainsFilter
                    && $query->getValue() === $filterValue
                    && $query->getField() === array_shift($fields)
                )
                ) {
                    return false;
                }
            }

            return true;
        });

        $this->appRepoMock->method('search')->with($criteria, static::anything());

        $commandTester = $this->executeCommand(['--filter' => $filterValue]);

        static::assertSame(0, $commandTester->getStatusCode());
        static::assertStringContainsString('Filtering for: ' . $filterValue, trim($commandTester->getDisplay()));
    }

    public function testJsonOutput(): void
    {
        $entities = [
            $app1 = new AppEntity(),
            $app2 = new AppEntity(),
        ];

        $app1->setUniqueIdentifier('1');
        $app2->setUniqueIdentifier('2');

        $this->setupEntityCollection($entities);

        $options = ['--json' => true];
        $json = json_encode([$app1->jsonSerialize(), $app2->jsonSerialize()], \JSON_THROW_ON_ERROR);

        $commandTester = $this->executeCommand($options);
        static::assertSame(0, $commandTester->getStatusCode());
        static::assertSame($json, trim($commandTester->getDisplay()));
    }

    /**
     * @param array<string, bool|string> $options
     */
    private function executeCommand(array $options): CommandTester
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute($options);

        return $commandTester;
    }

    /**
     * @param array<int, AppEntity> $entities
     */
    private function setupEntityCollection(array $entities): void
    {
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new AppCollection($entities));
        $this->appRepoMock->method('search')->willReturn($result);
    }
}
