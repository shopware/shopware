<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\ValueGenerator;

use Shopware\Core\System\NumberRange\NumberRangeEntity;

abstract class ValueGenerator implements ValueGeneratorInterface
{
    /**
     * @var NumberRangeEntity
     */
    protected $configuration;

    protected $configurationDefinitionClass;

    protected $generatorId = 'value_generator';

    abstract public function generate($value = null): string;

    public function incrementBy($lastIncrement = null): int
    {
        return 1;
    }

    /**
     * @return string
     */
    public function getGeneratorId(): string
    {
        return $this->generatorId;
    }

    /**
     * @param NumberRangeEntity $configuration
     */
    public function setConfiguration($configuration): void
    {
        $this->configuration = $configuration;
    }
}
