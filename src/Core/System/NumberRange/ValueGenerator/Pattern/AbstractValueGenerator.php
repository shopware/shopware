<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
abstract class AbstractValueGenerator
{
    /**
     * Resolves a specific subpattern. Takes the number range configuration and, if given, arguments
     * to modify the result in a pattern specific way. Returns only the part of the pattern it is responsible for and
     * don't even know the whole pattern
     *
     * @param array{id: string, pattern: string, start: ?int} $config
     */
    abstract public function generate(array $config, ?array $args = null, ?bool $preview = false): string;

    /**
     * returns the ID of the Pattern
     */
    abstract public function getPatternId(): string;

    abstract public function getDecorated(): self;
}
