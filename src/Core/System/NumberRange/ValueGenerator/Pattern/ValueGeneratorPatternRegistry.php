<?php
declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern;

use Shopware\Core\Framework\Feature;
use Shopware\Core\System\NumberRange\NumberRangeEntity;

class ValueGeneratorPatternRegistry
{
    /**
     * @var ValueGeneratorPatternInterface[]|AbstractValueGenerator[]
     */
    private $pattern = [];

    /**
     * @internal
     */
    public function __construct(iterable $patterns)
    {
        /** @var ValueGeneratorPatternInterface|AbstractValueGenerator $pattern */
        foreach ($patterns as $pattern) {
            $this->pattern[$pattern->getPatternId()] = $pattern;
        }
    }

    /**
     * @param array{id: string, pattern: string, start: ?int} $config
     */
    public function generatePattern(string $pattern, string $patternPart, array $config, ?array $args = null, ?bool $preview = false): string
    {
        $generator = $this->pattern[$pattern] ?? null;

        if (!$generator) {
            return $patternPart;
        }

        /**
         * @deprecated tag:v6.5.0 whole if part can be removed if we remove the ValueGeneratorPatternInterface
         */
        if (!$generator instanceof AbstractValueGenerator) {
            $entity = $this->getEntityFromConfig($config);

            return $generator->resolve($entity, $args, $preview);
        }

        return $generator->generate($config, $args, $preview);
    }

    /**
     * @deprecated tag:v6.5.0 will be removed
     */
    public function getPatternResolver(string $patternId): ?ValueGeneratorPatternInterface
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0', 'ValueGeneratorPatternRegistry::generatePattern()')
        );

        $generator = $this->pattern[$patternId] ?? null;
        if ($generator instanceof ValueGeneratorPatternInterface) {
            return $generator;
        }

        return null;
    }

    private function getEntityFromConfig(array $config): NumberRangeEntity
    {
        return (new NumberRangeEntity())
            ->assign($config);
    }
}
