<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata;

use Faker\Generator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Symfony\Component\Console\Style\SymfonyStyle;

class DemodataContext
{
    /**
     * @var Context
     */
    protected $context;

    /**
     * List of created entities for definition
     *
     * @var string[][]
     */
    protected $entities = [];

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

    public function __construct(Context $context, Generator $faker, string $projectDir, SymfonyStyle $console)
    {
        $this->context = $context;
        $this->faker = $faker;
        $this->projectDir = $projectDir;
        $this->console = $console;
    }

    public function add(string $definition, string ...$entityIds): void
    {
        foreach ($entityIds as $id) {
            $this->entities[$definition][] = $id;
        }
    }

    public function getIds(string $definition): array
    {
        return $this->entities[$definition] ?? [];
    }

    public function getRandomId(string $definition): ?string
    {
        if (empty($this->entities[$definition])) {
            return null;
        }

        return $this->entities[$definition][array_rand($this->entities[$definition])];
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getAllEntities(): array
    {
        return $this->entities;
    }

    public function getConsole(): SymfonyStyle
    {
        return $this->console;
    }

    public function getFaker(): Generator
    {
        return $this->faker;
    }

    /**
     * @param string|EntityDefinition $definition
     * @param int                     $numberOfItems
     * @param float                   $end
     */
    public function setTiming(string $definition, int $numberOfItems, float $end): void
    {
        $this->timings[$definition] = [
            'definition' => $definition::getEntityName(),
            'items' => $numberOfItems,
            'time' => $end,
        ];
    }

    public function getTimings(): array
    {
        return $this->timings;
    }

    public function getProjectDir()
    {
        return $this->projectDir;
    }
}
