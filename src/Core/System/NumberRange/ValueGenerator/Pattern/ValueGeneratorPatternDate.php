<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\NumberRange\NumberRangeEntity;

/**
 * @deprecated tag:v6.5.0 won't implement ValueGeneratorPatternInterface anymore
 */
class ValueGeneratorPatternDate extends AbstractValueGenerator implements ValueGeneratorPatternInterface
{
    public const STANDARD_FORMAT = 'Y-m-d';

    public function getPatternId(): string
    {
        return 'date';
    }

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

    /**
     * @deprecated tag:v6.5.0 will be removed, use `generate()` instead
     */
    public function resolve(NumberRangeEntity $configuration, ?array $args = null, ?bool $preview = false): string
    {
        Feature::throwException('v6.5.0.0', 'ValueGeneratorPatternInterface::resolve() will be removed, use `generate()` instead');

        $config = [
            'id' => $configuration->getId(),
            'start' => $configuration->getStart(),
            'pattern' => $configuration->getPattern() ?? '',
        ];

        return $this->generate($config, $args, $preview);
    }
}
