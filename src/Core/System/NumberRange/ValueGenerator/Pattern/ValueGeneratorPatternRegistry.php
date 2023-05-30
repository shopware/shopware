<?php
declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class ValueGeneratorPatternRegistry
{
    /**
     * @var AbstractValueGenerator[]
     */
    private array $pattern = [];

    /**
     * @internal
     *
     * @param AbstractValueGenerator[] $patterns
     */
    public function __construct(iterable $patterns)
    {
        /** @var AbstractValueGenerator $pattern */
        foreach ($patterns as $pattern) {
            $this->pattern[$pattern->getPatternId()] = $pattern;
        }
    }

    /**
     * @param array{id: string, pattern: string, start: ?int} $config
     * @param array<int, string>|null $args
     */
    public function generatePattern(string $pattern, string $patternPart, array $config, ?array $args = null, ?bool $preview = false): string
    {
        $generator = $this->pattern[$pattern] ?? null;

        if (!$generator) {
            return $patternPart;
        }

        return $generator->generate($config, $args, $preview);
    }
}
