<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('checkout')]
class ValueGeneratorPatternDate extends AbstractValueGenerator
{
    final public const STANDARD_FORMAT = 'Y-m-d';

    public function getPatternId(): string
    {
        return 'date';
    }

    /**
     * @param array<int, string>|null $args
     */
    public function generate(array $config, ?array $args = null, ?bool $preview = false): string
    {
        if ($args === null || \count($args) === 0) {
            $args[] = self::STANDARD_FORMAT;
        }

        return date($args[0]);
    }

    public function getDecorated(): AbstractValueGenerator
    {
        throw new DecorationPatternException(self::class);
    }
}
