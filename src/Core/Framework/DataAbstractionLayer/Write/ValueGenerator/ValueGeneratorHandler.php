<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\ValueGenerator;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\NumberRange\NumberRangeDefinition;
use Shopware\Core\System\NumberRange\NumberRangeEntity;

class ValueGeneratorHandler implements ValueGeneratorHandlerInterface
{
    /**
     * @var NumberRangeEntity
     */
    protected $configuration;

    /**
     * @var string
     */
    protected $configurationDefinitionClass;

    /**
     * @var ValueGeneratorConnectorRegistry
     */
    private $valueConnectorRegistry;

    /**
     * @var ValueGeneratorConnectorInterface
     */
    private $valueConnector;

    /**
     * @var ValueGeneratorRegistry
     */
    private $valueGeneratorRegistry;

    /**
     * @var ValueGeneratorInterface
     */
    private $valueGenerator;

    /**
     * @var EntityReaderInterface
     */
    private $entityReader;
    /**
     * @var Context
     */
    private $context;

    private $initialized = false;

    public function __construct(
            ValueGeneratorConnectorRegistry $valueConnectorRegistry,
            ValueGeneratorRegistry $generatorRegistry,
            EntityReaderInterface $entityReader)
    {
        $this->valueConnectorRegistry = $valueConnectorRegistry;
        $this->entityReader = $entityReader;
        $this->valueGeneratorRegistry = $generatorRegistry;
    }

    /**
     * generates a new Value while taking Care of States, Events and Connectors.
     *
     * @return string
     */
    final public function getValue(): string
    {
        $this->init();

        $state = $this->valueConnector->pullState();

        $startState = $this->startEvent($state);

        $generatedValue = $this->valueGenerator->generate($startState);

        $value = $this->endEvent($generatedValue);

        return (string) $value;
    }

    /**
     * @param Context $context
     */
    public function setContext(Context $context): void
    {
        $this->context = $context;
    }

    protected function readConfiguration(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'test'));
        $criteria->setLimit(1);
        $configurationCollection = $this->entityReader->read(
            NumberRangeDefinition::class, $criteria, $this->context
        );
        if ($configurationCollection->count() === 1) {
            $this->configuration = $configurationCollection->first();
        } else {
            $this->configuration = new NumberRangeEntity();
        }
    }

    protected function setValueGeneratorConnector(): void
    {
        $this->valueConnector = $this->valueConnectorRegistry->getConnector($this->configuration->getConnectorType());
    }

    protected function setValueGenerator(): void
    {
        $this->valueGenerator = $this->valueGeneratorRegistry->getGenerator($this->configuration->getGeneratorType());
        $this->valueGenerator->setConfiguration($this->configuration);
    }

    protected function startEvent($value)
    {
        return $value;
    }

    protected function endEvent($value)
    {
        return $value;
    }

    private function init(): void
    {
        if ($this->initialized === false) {
            $this->readConfiguration();
            $this->setValueGenerator();
            $this->setValueGeneratorConnector();
            $this->valueConnector->setGenerator($this->valueGenerator);
        }
    }
}
