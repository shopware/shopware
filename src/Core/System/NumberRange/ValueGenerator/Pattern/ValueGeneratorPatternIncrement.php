<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;

#[Package('checkout')]
class ValueGeneratorPatternIncrement extends AbstractValueGenerator
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractIncrementStorage $incrementConnector)
    {
    }

    public function getPatternId(): string
    {
        return 'n';
    }

    /**
     * @param array<int, string> $args
     */
    public function generate(array $config, ?array $args = null, ?bool $preview = false): string
    {
        if ($preview === true) {
            return (string) $this->incrementConnector->preview($config);
        }

        return (string) $this->incrementConnector->reserve($config);
    }

    public function getDecorated(): AbstractValueGenerator
    {
        throw new DecorationPatternException(self::class);
    }
}
