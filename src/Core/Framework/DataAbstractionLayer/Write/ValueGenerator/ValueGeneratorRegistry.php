<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\ValueGenerator;

class ValueGeneratorRegistry
{
    /**
     * @var ValueGeneratorInterface[]
     */
    protected $generators = [];

    /**
     * @var ValueGeneratorInterface[]
     */
    protected $mapped;

    public function __construct(iterable $generators)
    {
        $this->generators = $generators;
    }

    public function getGenerator($generatorId): ?ValueGeneratorInterface
    {
        if ($generatorId === null) {
            $generatorId = 'number_range_value_generator';
        }
        $this->mapGenerators();

        return $this->mapped[$generatorId] ?? null;
    }

    private function mapGenerators(): array
    {
        if ($this->mapped === null) {
            $this->mapped = [];

            /* @var ValueGeneratorInterface $serializer */
            foreach ($this->generators as $generator) {
                $this->mapped[$generator->getGeneratorId()] = $generator;
            }
        }

        return $this->mapped;
    }
}
