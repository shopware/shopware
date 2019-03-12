<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern;

use Shopware\Core\System\NumberRange\NumberRangeEntity;

interface ValueGeneratorPatternInterface
{
    /**
     * Resolves a specific subpattern. Takes the number range configuration and, if given, arguments
     * to modify the result in a pattern specific way. Returns only the part of the pattern it is responsible for and
     * don't even know the whole pattern
     */
    public function resolve(NumberRangeEntity $configuration, ?array $args = null, ?bool $preview = false): string;

    /**
     * returns the ID of the Pattern
     */
    public function getPatternId(): string;
}
