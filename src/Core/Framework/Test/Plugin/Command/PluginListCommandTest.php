<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Plugin\Command\PluginListCommand;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class PluginListCommandTest extends TestCase
{
    private MockObject&EntityRepository $pluginRepoMock;

    private PluginListCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pluginRepoMock = $this->createMock(EntityRepository::class);
        $this->command = new PluginListCommand($this->pluginRepoMock);
    }

    public function testCommand(): void
    {
        $plugin1 = new PluginEntity();
        $plugin2 = new PluginEntity();

        $entities = [
            $plugin1,
            $plugin2,
        ];

        $plugin1->setUniqueIdentifier('1');
        $plugin1->assign([
            'active' => true,
            'installedAt' => new \DateTimeImmutable('2004-01-01T00:00:00.000001Z'),
            'upgradeVersion' => '3.0.1',
            'name' => 'Plugin List Plugin',
            'label' => 'plp',
            'version' => '2.5.3',
            'author' => 'Fabian Blechschmidt',
        ]);

        $plugin2->setUniqueIdentifier('2');
        $plugin2->assign([
            'active' => false,
            'installedAt' => new \DateTimeImmutable('2019-05-23T00:00:00.000001Z'),
            'upgradeVersion' => '6.0.0',
            'name' => 'Shopware Next',
            'label' => 'swn',
            'version' => '5.5.3',
            'author' => 'Shopware AG',
        ]);

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
        $entities = [
            $plugin1 = new PluginEntity(),
            $plugin2 = new PluginEntity(),
        ];

        $plugin1->setUniqueIdentifier('1');
        $plugin2->setUniqueIdentifier('2');

        $this->setupEntityCollection($entities);

        $options = ['--json' => true];
        $json = json_encode([$plugin1->jsonSerialize(), $plugin2->jsonSerialize()], \JSON_THROW_ON_ERROR);

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
     * @param PluginEntity[] $entities
     */
    private function setupEntityCollection(array $entities): void
    {
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new PluginCollection($entities));
        $this->pluginRepoMock->method('search')->willReturn($result);
    }
}
