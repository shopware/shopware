<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\NumberRange\NoConfigurationException;
use Shopware\Core\System\Event\NumberRangeEvents;
use Shopware\Core\System\NumberRange\NumberRangeDefinition;
use Shopware\Core\System\NumberRange\NumberRangeEntity;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class NumberRangeValueGenerator implements NumberRangeValueGeneratorInterface
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
     * @var ValueGeneratorPatternRegistry
     */
    private $valueGeneratorPatternRegistry;

    /**
     * @var EntityReaderInterface
     */
    private $entityReader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        ValueGeneratorPatternRegistry $valueGeneratorPatternRegistry,
        EntityReaderInterface $entityReader,
        EventDispatcherInterface $eventDispatcher)
    {
        $this->entityReader = $entityReader;
        $this->valueGeneratorPatternRegistry = $valueGeneratorPatternRegistry;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * generates a new Value while taking Care of States, Events and Connectors
     */
    public function getValue(string $definition, Context $context, ?string $salesChannelId): string
    {
        $this->readConfiguration($definition, $context, $salesChannelId);

        $parsedPattern = $this->parsePattern($this->configuration->getPattern());

        $generatedValue = $this->generate($parsedPattern);

        return $this->endEvent($generatedValue);
    }

    protected function parsePattern($pattern): ?array
    {
        return preg_split(
            '/([}{])/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        ) ?? null;
    }

    protected function readConfiguration(string $definition, Context $context, ?string $salesChannelId): void
    {
        /** @var EntityDefinition $definition */
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_OR,
                [
                    new MultiFilter(
                        MultiFilter::CONNECTION_AND, [
                            new EqualsFilter('number_range.salesChannels.id', $salesChannelId),
                            new EqualsFilter('number_range.entity.entityName', $definition::getEntityName()),
                        ]
                    ),
                    new MultiFilter(
                        MultiFilter::CONNECTION_AND, [
                            new EqualsFilter('number_range.entity.global', 1),
                            new EqualsFilter('number_range.entity.entityName', $definition::getEntityName()),
                        ]
                    ),
                ]
            )
        );
        $criteria->setLimit(1);

        $configurationCollection = $this->entityReader->read(
            NumberRangeDefinition::class, $criteria, $context
        );

        if ($configurationCollection->count() === 1) {
            $this->configuration = $configurationCollection->first();
        } else {
            throw new NoConfigurationException($definition::getEntityName(), $salesChannelId);
        }
    }

    protected function endEvent($generatedValue): string
    {
        /** @var NumberRangeGeneratedEvent $generatedEvent */
        $generatedEvent = $this->eventDispatcher->dispatch(
            NumberRangeEvents::NUMBER_RANGE_GENERATED,
            new NumberRangeGeneratedEvent($generatedValue)
        );

        return $generatedEvent->getGeneratedValue();
    }

    private function generate(?array $parsedPattern): string
    {
        $generated = '';
        $startPattern = false;
        foreach ($parsedPattern as $patternPart) {
            if ($patternPart === '}') {
                $startPattern = false;
                continue;
            }
            if ($patternPart === '{') {
                $startPattern = true;
                continue;
            }
            if ($startPattern === true) {
                $patternArg = explode('_', $patternPart);
                $pattern = array_shift($patternArg);
                $patternResolver = $this->valueGeneratorPatternRegistry->getPatternResolver($pattern);
                if ($patternResolver) {
                    $generated .= $patternResolver->resolve($this->configuration, $patternArg);
                } else {
                    // throw warning...
                    $generated .= $patternPart;
                }
                $startPattern = false;
                continue;
            }
            $generated .= $patternPart;
        }

        return $generated;
    }
}
