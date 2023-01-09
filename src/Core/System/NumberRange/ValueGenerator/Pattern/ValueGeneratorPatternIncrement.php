<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern;

use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;

/**
 * @package checkout
 */
class ValueGeneratorPatternIncrement extends AbstractValueGenerator
{
    private AbstractIncrementStorage $incrementConnector;

    /**
     * @internal
     */
    public function __construct(AbstractIncrementStorage $incrementConnector)
    {
        $this->incrementConnector = $incrementConnector;
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
