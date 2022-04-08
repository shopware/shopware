<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern;

use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\NumberRange\NumberRangeEntity;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementStorageInterface;

/**
 * @deprecated tag:v6.5.0 won't implement ValueGeneratorPatternInterface anymore
 */
class ValueGeneratorPatternIncrement extends AbstractValueGenerator implements ValueGeneratorPatternInterface
{
    /**
     * @var IncrementStorageInterface|AbstractIncrementStorage
     */
    private $incrementConnector;

    /**
     * @param IncrementStorageInterface|AbstractIncrementStorage $incrementConnector
     *
     * @deprecated tag:v6.5.0 incrementConnector will require a AbstractIncrementStorage
     */
    public function __construct($incrementConnector)
    {
        $this->incrementConnector = $incrementConnector;
    }

    public function getPatternId(): string
    {
        return 'n';
    }

    public function generate(array $config, ?array $args = null, ?bool $preview = false): string
    {
        /**
         * @deprecated tag:v6.5.0 whole if statement can be removed if we remove IncrementStorageInterface
         */
        if (!$this->incrementConnector instanceof AbstractIncrementStorage) {
            $entity = $this->getEntityFromConfig($config);

            if ($preview === true) {
                return $this->incrementConnector->getNext($entity);
            }

            return $this->incrementConnector->pullState($entity);
        }

        if ($preview === true) {
            return (string) $this->incrementConnector->preview($config);
        }

        return (string) $this->incrementConnector->reserve($config);
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
        $config = [
            'id' => $configuration->getId(),
            'start' => $configuration->getStart(),
            'pattern' => $configuration->getPattern() ?? '',
        ];

        return $this->generate($config, $args, $preview);
    }

    private function getEntityFromConfig(array $config): NumberRangeEntity
    {
        return (new NumberRangeEntity())
            ->assign($config);
    }
}
