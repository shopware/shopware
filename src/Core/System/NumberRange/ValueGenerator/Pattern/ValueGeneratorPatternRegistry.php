<?php
declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern;

class ValueGeneratorPatternRegistry
{
    /**
     * @var ValueGeneratorPatternInterface[]
     */
    private $pattern = [];

    /**
     * @var ValueGeneratorPatternInterface[]
     */
    private $mapped;

    public function __construct(iterable $pattern)
    {
        $this->pattern = $pattern;
    }

    public function getPatternResolver(string $patternId): ?ValueGeneratorPatternInterface
    {
        $this->mapPatternResolvers();

        return $this->mapped[$patternId] ?? null;
    }

    private function mapPatternResolvers(): array
    {
        if ($this->mapped === null) {
            $this->mapped = [];

            foreach ($this->pattern as $singlePattern) {
                $this->mapped[$singlePattern->getPatternId()] = $singlePattern;
            }
        }

        return $this->mapped;
    }
}
