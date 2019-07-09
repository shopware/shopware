<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata;

use Doctrine\DBAL\Connection;
use Faker\Generator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\PaginationCriteria;
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

    /**
     * @var DefinitionInstanceRegistry
     */
    private $registry;

    public function __construct(
        Connection $connection,
        Context $context,
        Generator $faker,
        string $projectDir,
        SymfonyStyle $console,
        DefinitionInstanceRegistry $registry
    ) {
        $this->context = $context;
        $this->faker = $faker;
        $this->projectDir = $projectDir;
        $this->console = $console;
        $this->connection = $connection;
        $this->registry = $registry;
    }

    public function getIds(string $entity): array
    {
        if (!empty($this->entities[$entity])) {
            return $this->entities[$entity];
        }

        $repository = $this->registry->getRepository($entity);

        $ids = $repository->searchIds(new PaginationCriteria(500), Context::createDefaultContext())
            ->getIds();

        $this->entities[$entity] = $ids;

        return $this->entities[$entity];
    }

    public function getRandomId(string $entity): ?string
    {
        $ids = $this->getIds($entity);

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
