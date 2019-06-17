<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata;

use Doctrine\DBAL\Connection;
use Faker\Generator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Symfony\Component\Console\Style\SymfonyStyle;

class DemodataContext
{
    /**
     * @var Context
     */
    private $context;

    /**
     * List of created entities for definition
     *
     * @var string[][]
     */
    private $entities = [];

    /**
     * @var SymfonyStyle
     */
    private $console;

    /**
     * @var Generator
     */
    private $faker;

    /**
     * @var array[]
     */
    private $timings;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        Connection $connection,
        Context $context,
        Generator $faker,
        string $projectDir,
        SymfonyStyle $console
    ) {
        $this->context = $context;
        $this->faker = $faker;
        $this->projectDir = $projectDir;
        $this->console = $console;
        $this->connection = $connection;
    }

    public function getIds(string $table): array
    {
        if (!empty($this->entities[$table])) {
            return $this->entities[$table];
        }

        $ids = $this->connection->fetchAll('SELECT LOWER(HEX(id)) as id FROM ' . $table . ' LIMIT 500');

        $this->entities[$table] = array_column($ids, 'id');

        return $this->entities[$table];
    }

    public function getRandomId(string $table): ?string
    {
        $ids = $this->getIds($table);

        return $this->faker->randomElement($ids);
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getConsole(): SymfonyStyle
    {
        return $this->console;
    }

    public function getFaker(): Generator
    {
        return $this->faker;
    }

    public function setTiming(EntityDefinition $definition, int $numberOfItems, float $end): void
    {
        $this->timings[$definition->getClass()] = [
            'definition' => $definition->getEntityName(),
            'items' => $numberOfItems,
            'time' => $end,
        ];
    }

    public function getTimings(): array
    {
        return $this->timings;
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }
}
