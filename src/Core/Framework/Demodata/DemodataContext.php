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
     * @var DefinitionInstanceRegistry
     */
    private $registry;

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

        $ids = $repository->searchIds($criteria, Context::createDefaultContext())
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
