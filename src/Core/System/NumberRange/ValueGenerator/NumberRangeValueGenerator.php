<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeEntity;
use Shopware\Core\System\NumberRange\Exception\NoConfigurationException;
use Shopware\Core\System\NumberRange\NumberRangeDefinition;
use Shopware\Core\System\NumberRange\NumberRangeEntity;
use Shopware\Core\System\NumberRange\NumberRangeEvents;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class NumberRangeValueGenerator implements NumberRangeValueGeneratorInterface
{
    /**
     * @var NumberRangeEntity
     */
    private $configuration;

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

    /**
     * @var NumberRangeDefinition
     */
    private $numberRangeDefinition;

    public function __construct(
        ValueGeneratorPatternRegistry $valueGeneratorPatternRegistry,
        EntityReaderInterface $entityReader,
        EventDispatcherInterface $eventDispatcher,
        NumberRangeDefinition $numberRangeDefinition
    ) {
        $this->entityReader = $entityReader;
        $this->valueGeneratorPatternRegistry = $valueGeneratorPatternRegistry;
        $this->eventDispatcher = $eventDispatcher;
        $this->numberRangeDefinition = $numberRangeDefinition;
    }

    public function getValue(string $type, Context $context, ?string $salesChannelId, bool $preview = false): string
    {
        $this->readConfiguration($type, $context, $salesChannelId);

        $parsedPattern = $this->parsePattern($this->configuration->getPattern());

        $generatedValue = $this->generate($parsedPattern, $preview);

        return $this->endEvent($generatedValue, $type, $context, $salesChannelId, $preview);
    }

    public function previewPattern(string $definition, ?string $pattern, int $start): string
    {
        $this->createPreviewConfiguration($definition, $pattern, $start);

        $parsedPattern = $this->parsePattern($this->configuration->getPattern());

        return $this->generate($parsedPattern, true);
    }

    protected function parsePattern(?string $pattern): ?array
    {
        return preg_split(
            '/([}{])/',
            $pattern,
            -1,
            \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY
        );
    }

    protected function readConfiguration(string $definition, Context $context, ?string $salesChannelId): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_OR,
                [
                    new MultiFilter(
                        MultiFilter::CONNECTION_AND,
                        [
                            new EqualsFilter('number_range.numberRangeSalesChannels.salesChannelId', $salesChannelId),
                            new EqualsFilter('number_range.type.technicalName', $definition),
                        ]
                    ),
                    new MultiFilter(
                        MultiFilter::CONNECTION_AND,
                        [
                            new EqualsFilter('number_range.type.global', 1),
                            new EqualsFilter('number_range.type.technicalName', $definition),
                        ]
                    ),
                ]
            )
        );
        $criteria->setLimit(1);

        $configurationCollection = $this->entityReader->read(
            $this->numberRangeDefinition,
            $criteria,
            $context
        );

        if ($configurationCollection->count() === 1) {
            $this->configuration = $configurationCollection->first();
        } else {
            //get Fallback Configuration
            $criteria = new Criteria();
            $criteria->addFilter(
                new MultiFilter(
                    MultiFilter::CONNECTION_AND,
                    [
                        new EqualsFilter('number_range.global', 1),
                        new EqualsFilter('number_range.type.technicalName', $definition),
                    ]
                )
            );
            $criteria->setLimit(1);

            $configurationCollection = $this->entityReader->read(
                $this->numberRangeDefinition,
                $criteria,
                $context
            );

            if ($configurationCollection->count() === 1) {
                $this->configuration = $configurationCollection->first();
            } else {
                throw new NoConfigurationException($definition, $salesChannelId);
            }
        }
    }

    protected function createPreviewConfiguration(string $definition, ?string $pattern, int $start): void
    {
        $entity = new NumberRangeTypeEntity();
        $entity->setTechnicalName($definition);
        $entity->setGlobal(true);
        $this->configuration = new NumberRangeEntity();
        $this->configuration->setId(Uuid::randomHex());
        $this->configuration->setName('preview');
        $this->configuration->setType($entity);
        $this->configuration->setCreatedAt(new \DateTime());
        $this->configuration->setUpdatedAt(new \DateTime());
        $this->configuration->setPattern($pattern);
        $this->configuration->setStart($start);
    }

    protected function endEvent(string $generatedValue, string $type, Context $context, ?string $salesChannelId, bool $preview): string
    {
        /** @var NumberRangeGeneratedEvent $generatedEvent */
        $generatedEvent = $this->eventDispatcher->dispatch(
            new NumberRangeGeneratedEvent($generatedValue, $type, $context, $salesChannelId, $preview),
            NumberRangeEvents::NUMBER_RANGE_GENERATED
        );

        return $generatedEvent->getGeneratedValue();
    }

    private function generate(?array $parsedPattern, ?bool $preview = false): string
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
                    $generated .= $patternResolver->resolve($this->configuration, $patternArg, $preview);
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
