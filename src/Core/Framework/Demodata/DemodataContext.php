<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata;

use Faker\Generator;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @package core
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - will be internal in 6.5.0
 */
class DemodataContext
{
    private Context $context;

    /**
     * List of created entities for definition
     *
     * @var array<string, list<string>>
     */
    private array $entities = [];

    private SymfonyStyle $console;

    private Generator $faker;

    /**
     * @var array<string, array{definition: string, items: int, time: float}>
     */
    private array $timings;

    private string $projectDir;

    private DefinitionInstanceRegistry $registry;

    public function __construct(
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
        $this->registry = $registry;
    }

    /**
     * @return list<string>
     */
    public function getIds(string $entity): array
    {
        if (!empty($this->entities[$entity])) {
            return $this->entities[$entity];
        }

        $repository = $this->registry->getRepository($entity);

        $criteria = new Criteria();
        if ($entity === MediaDefinition::ENTITY_NAME) {
            $criteria->addFilter(new EqualsFilter('mediaFolder.defaultFolder.entity', 'product'));
        }
        $criteria->setLimit(500);

        /** @var list<string> $ids */
        $ids = $repository->searchIds($criteria, Context::createDefaultContext())
            ->getIds();

        return $this->entities[$entity] = $ids;
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

    /**
     * @return array<string, array{definition: string, items: int, time: float}>
     */
    public function getTimings(): array
    {
        return $this->timings;
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }
}
