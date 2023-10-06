<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\Exception\NoConfigurationException;
use Shopware\Core\System\NumberRange\NumberRangeEvents;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
class NumberRangeValueGenerator implements NumberRangeValueGeneratorInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ValueGeneratorPatternRegistry $valueGeneratorPatternRegistry,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Connection $connection
    ) {
    }

    public function getValue(string $type, Context $context, ?string $salesChannelId, bool $preview = false): string
    {
        $config = $this->getConfiguration($type, $salesChannelId);

        $parsedPattern = $this->parsePattern($config['pattern']);

        $generatedValue = \is_array($parsedPattern) ? $this->generate($parsedPattern, $config, $preview) : '';

        return $this->endEvent($generatedValue, $type, $context, $salesChannelId, $preview);
    }

    public function previewPattern(string $definition, ?string $pattern, int $start): string
    {
        $config = $this->getConfiguration($definition, null);
        $config['start'] = $start;

        if (!$pattern) {
            $pattern = $config['pattern'];
        }

        $parsedPattern = $this->parsePattern($pattern);

        return \is_array($parsedPattern) ? $this->generate($parsedPattern, $config, true) : '';
    }

    /**
     * @return array<string>|null
     */
    private function parsePattern(?string $pattern): ?array
    {
        if (!$pattern) {
            return null;
        }

        return preg_split(
            '/([}{])/',
            $pattern,
            -1,
            \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY
        ) ?: null;
    }

    private function endEvent(string $generatedValue, string $type, Context $context, ?string $salesChannelId, bool $preview): string
    {
        /** @var NumberRangeGeneratedEvent $generatedEvent */
        $generatedEvent = $this->eventDispatcher->dispatch(
            new NumberRangeGeneratedEvent($generatedValue, $type, $context, $salesChannelId, $preview),
            NumberRangeEvents::NUMBER_RANGE_GENERATED
        );

        return $generatedEvent->getGeneratedValue();
    }

    /**
     * @return array{id: string, pattern: string, start: ?int}
     */
    private function getConfiguration(string $definition, ?string $salesChannelId): array
    {
        if ($salesChannelId) {
            /** @var array{id: string, pattern: string, start: ?int}|false $config */
            $config = $this->connection->fetchAssociative('
                SELECT LOWER(HEX(`number_range`.`id`)) AS `id`, `number_range`.`pattern`, `number_range`.`start`
                FROM number_range
                INNER JOIN number_range_type ON number_range_type.id = number_range.type_id
                LEFT JOIN number_range_sales_channel ON number_range.id = number_range_sales_channel.number_range_id
                WHERE `number_range_type`.`technical_name` = :typeName AND (
                    number_range_sales_channel.sales_channel_id = :salesChannelId OR number_range_type.global = 1 OR number_range.global = 1
                )
                ORDER BY number_range.global ASC, number_range_type.global ASC
            ', ['typeName' => $definition, 'salesChannelId' => Uuid::fromHexToBytes($salesChannelId)]);
        } else {
            /** @var array{id: string, pattern: string, start: ?int}|false $config */
            $config = $this->connection->fetchAssociative('
                SELECT LOWER(HEX(`number_range`.`id`)) AS `id`, `number_range`.`pattern`, `number_range`.`start`
                FROM number_range
                INNER JOIN number_range_type ON number_range_type.id = number_range.type_id
                WHERE `number_range_type`.`technical_name` = :typeName AND (number_range_type.global = 1 OR number_range.global = 1)
                ORDER BY number_range.global ASC
            ', ['typeName' => $definition]);
        }

        if (!$config) {
            throw new NoConfigurationException($definition, $salesChannelId);
        }

        if ($config['start']) {
            $config['start'] = (int) $config['start'];
        }

        return $config;
    }

    /**
     * @param array{id: string, pattern: string, start: ?int} $config
     * @param array<string> $parsedPattern
     */
    private function generate(array $parsedPattern, array $config, ?bool $preview = false): string
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
                $generated .= $this->valueGeneratorPatternRegistry->generatePattern($pattern, $patternPart, $config, $patternArg, $preview);

                $startPattern = false;

                continue;
            }
            $generated .= $patternPart;
        }

        return $generated;
    }
}
