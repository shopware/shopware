<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Plugin\Command\PluginListCommand;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Symfony\Component\Console\Tester\CommandTester;

class PluginListCommandTest extends TestCase
{
    /**
     * @var MockObject|EntityRepository
     */
    private $pluginRepoMock;

    private PluginListCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pluginRepoMock = $this->createMock(EntityRepository::class);
        $this->command = new PluginListCommand($this->pluginRepoMock);
    }

    public function testCommand(): void
    {
        $entities = [
            $this->createMock(PluginEntity::class),
            $this->createMock(PluginEntity::class),
        ];
        $config = [
            [
                'getActive' => true,
                'getInstalledAt' => new \DateTimeImmutable('2004-01-01T00:00:00.000001Z'),
                'getUpgradeVersion' => '3.0.1',
                'getName' => 'Plugin List Plugin',
                'getLabel' => 'plp',
                'getVersion' => '2.5.3',
                'getAuthor' => 'Fabian Blechschmidt',
            ],
            [
                'getActive' => false,
                'getInstalledAt' => new \DateTimeImmutable('2019-05-23T00:00:00.000001Z'),
                'getUpgradeVersion' => '6.0.0',
                'getName' => 'Shopware Next',
                'getLabel' => 'swn',
                'getVersion' => '5.5.3',
                'getAuthor' => 'Shopware AG',
            ],
        ];

        foreach ($config as $number => $methods) {
            foreach ($methods as $method => $value) {
                $plugin = $entities[$number];
                $plugin->method($method)->willReturn($value);
            }
        }

        $this->setupEntityCollection($entities);

        $commandTester = $this->executeCommand([]);
        static::assertSame(0, $commandTester->getStatusCode());
        static::assertStringEqualsFile(
            __DIR__ . '/../_assertion/PluginListCommandTest-testCommand.txt',
            implode("\n", array_map('trim', explode("\n", trim($commandTester->getDisplay())))) . "\n"
        );
    }

    public function testFilter(): void
    {
        $filterValue = 'shopware-is-love';

        $criteria = static::callback(function (Criteria $criteria) use ($filterValue): bool {
            $filters = $criteria->getFilters();
            // must be MultiFilter
            if (!(\count($filters) === 1 && $filters[0] instanceof MultiFilter)) {
                return false;
            }
            /** @var MultiFilter $filter */
            $filter = $filters[0];
            // must be OR
            if ($filter->getOperator() !== MultiFilter::CONNECTION_OR) {
                return false;
            }
            $fields = ['name', 'label'];
            foreach ($filter->getQueries() as $query) {
                /** @var ContainsFilter $query */
                if (!(
                    $query instanceof ContainsFilter
                    && $query->getValue() === $filterValue
                    // first test against name, then label
                    && $query->getField() === array_shift($fields)
                )
                ) {
                    return false;
                }
            }

            return true;
        });

        $this->pluginRepoMock->method('search')->with($criteria, static::anything());

        $commandTester = $this->executeCommand(['--filter' => $filterValue]);

        static::assertSame(0, $commandTester->getStatusCode());
        static::assertStringContainsString('Filtering for: ' . $filterValue, trim($commandTester->getDisplay()));
    }

    public function testJsonOutput(): void
    {
        $o1 = ['PLUGIN1'];
        $o2 = ['PLUGIN2'];

        $entities = [
            $plugin1 = $this->createMock(PluginEntity::class),
            $plugin2 = $this->createMock(PluginEntity::class),
        ];

        $plugin1->method('jsonSerialize')->willReturn($o1);
        $plugin2->method('jsonSerialize')->willReturn($o2);

        $this->setupEntityCollection($entities);

        $options = ['--json' => true];
        $json = json_encode([$o1, $o2], \JSON_THROW_ON_ERROR);

        $commandTester = $this->executeCommand($options);
        static::assertSame(0, $commandTester->getStatusCode());
        static::assertSame($json, trim($commandTester->getDisplay()));
    }

    private function executeCommand(array $options): CommandTester
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute($options);

        return $commandTester;
    }

    private function setupEntityCollection(array $entities): void
    {
        $collection = $this->createMock(EntityCollection::class);
        $collection->method('jsonSerialize')->willReturn($entities);
        $collection->method('getIterator')->willReturn($this->arrayAsGenerator($entities));
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn($collection);
        $this->pluginRepoMock->method('search')->willReturn($result);
    }

    private function arrayAsGenerator(array $array): \Generator
    {
        foreach ($array as $item) {
            yield $item;
        }
    }
}
