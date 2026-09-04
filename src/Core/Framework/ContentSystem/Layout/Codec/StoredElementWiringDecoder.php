<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Codec;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IndexedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IteratorDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\KeyedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\SlicedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Rendering\WiringPlanner;
use Shopware\Core\Framework\Log\Package;

/**
 * The decode side of one element's context wiring: the raw `providesContext` map into
 * {@see ContextProvider}s, the raw `acceptsContext` map into {@see ContextConsumer}s, and the element-local
 * rules that judge the finished maps against each other. {@see StoredElementCodec} delegates all three and
 * keeps everything else about the element wire shape.
 *
 * Wiring is judged in two tiers and first hit throws. Per consumer, inside {@see decodeConsumers()}: a
 * `consumerAlias` without `redistribute`, and a `propertyAlias` carrying dot notation. Then, once that map is
 * complete, the element-local tier in {@see rejectInvalidElementWiring()}: base-key uniqueness across the
 * consumer map, a `redistribute` consumer keyed by a dotted path, and a `redistribute` consumer whose derived
 * provider key an authored provider already holds. The tiers are ordered, not interleaved, so a per-consumer
 * violation anywhere in the element throws before an element-local one. A stored row already carrying an
 * element-local violation is unreadable here rather than repaired.
 *
 * A consumer entry's key set is closed exactly as the codec closes the element and data-requirement sets. A
 * provider entry is the one map that does carry extra keys: the declared distribution strategy's own fields
 * ride alongside `type` and `distribution`, and the strategy's config object reads them. Those strategy
 * fields are, in turn, judged by the distribution config's own `fromArray()`, which substitutes its declared
 * default for a field that is absent or null but rejects a present one of the wrong type; this class neither
 * validates nor repeats that judgement itself.
 *
 * The write-side counterpart is {@see StoredTreeWiringConstraints} and the render-side one is
 * {@see WiringPlanner}. The three stay independent implementations of the same rules; StoredTreeShapeConformanceTest
 * runs this side and the descriptor side over one payload table to catch a divergence sharing would hide.
 * {@see rejectUnknownKeys()} and {@see stringKeyed()} are duplicated from {@see StoredElementCodec} rather
 * than borrowed from it, so this class carries no collaborator.
 *
 * @internal
 */
#[Package('framework')]
final class StoredElementWiringDecoder
{
    /**
     * @var list<string>
     */
    private const CONSUMER_KEYS = [
        'type',
        'required',
        'redistribute',
        'consumerAlias',
        'propertyAlias',
    ];

    /**
     * @return array<string, ContextProvider>
     */
    public function decodeProviders(mixed $raw): array
    {
        if (!\is_array($raw)) {
            throw ContentSystemException::invalidFieldValueType('providesContext', 'array', get_debug_type($raw));
        }

        $providers = [];
        foreach ($raw as $key => $config) {
            if (!\is_string($key)) {
                throw ContentSystemException::invalidMapKey('Element context provider map', get_debug_type($key));
            }

            $path = \sprintf('providesContext[%s]', $key);

            if (!\is_array($config)) {
                throw ContentSystemException::invalidFieldValueType($path, 'array', get_debug_type($config));
            }

            $type = $config['type'] ?? null;
            $contextType = \is_string($type) ? ContextType::tryFrom($type) : null;
            if ($contextType === null) {
                throw ContentSystemException::invalidFieldValueType(
                    $path . '.type',
                    implode('|', ContextType::values()),
                    get_debug_type($type)
                );
            }

            $distribution = $config['distribution'] ?? null;
            $strategy = \is_string($distribution) ? DistributionStrategy::tryFrom($distribution) : null;
            if ($strategy === null) {
                throw ContentSystemException::invalidFieldValueType(
                    $path . '.distribution',
                    implode('|', DistributionStrategy::values()),
                    get_debug_type($distribution)
                );
            }

            $providers[$key] = new ContextProvider(
                $contextType,
                $this->distributionConfig($strategy, $this->stringKeyed($config, $path))
            );
        }

        return $providers;
    }

    /**
     * @return array<string, ContextConsumer>
     */
    public function decodeConsumers(mixed $raw): array
    {
        if (!\is_array($raw)) {
            throw ContentSystemException::invalidFieldValueType('acceptsContext', 'array', get_debug_type($raw));
        }

        $consumers = [];
        foreach ($raw as $key => $config) {
            if (!\is_string($key)) {
                throw ContentSystemException::invalidMapKey('Element context consumer map', get_debug_type($key));
            }

            $path = \sprintf('acceptsContext[%s]', $key);

            if (!\is_array($config)) {
                throw ContentSystemException::invalidFieldValueType($path, 'array', get_debug_type($config));
            }

            $this->rejectUnknownKeys($config, self::CONSUMER_KEYS, $path, 'consumer');

            $type = $config['type'] ?? null;
            $contextType = \is_string($type) ? ContextType::tryFrom($type) : null;
            if ($contextType === null) {
                throw ContentSystemException::invalidFieldValueType(
                    $path . '.type',
                    implode('|', ContextType::values()),
                    get_debug_type($type)
                );
            }

            $required = $config['required'] ?? null;
            if (!\is_bool($required)) {
                throw ContentSystemException::invalidFieldValueType($path . '.required', 'bool', get_debug_type($required));
            }

            $redistribute = $config['redistribute'] ?? false;
            if (!\is_bool($redistribute)) {
                throw ContentSystemException::invalidFieldValueType($path . '.redistribute', 'bool', get_debug_type($redistribute));
            }

            $consumerAlias = $config['consumerAlias'] ?? null;
            if ($consumerAlias !== null && !\is_string($consumerAlias)) {
                throw ContentSystemException::invalidFieldValueType($path . '.consumerAlias', 'string', get_debug_type($consumerAlias));
            }

            $propertyAlias = $config['propertyAlias'] ?? null;
            if ($propertyAlias !== null && !\is_string($propertyAlias)) {
                throw ContentSystemException::invalidFieldValueType($path . '.propertyAlias', 'string', get_debug_type($propertyAlias));
            }

            if ($consumerAlias !== null && !$redistribute) {
                throw ContentSystemException::consumerAliasWithoutRedistribute($key);
            }

            if ($propertyAlias !== null && str_contains($propertyAlias, '.')) {
                throw ContentSystemException::propertyAliasWithDotNotation($key, $propertyAlias);
            }

            $consumers[$key] = new ContextConsumer(
                type: $contextType,
                required: $required,
                redistribute: $redistribute,
                consumerAlias: $consumerAlias,
                propertyAlias: $propertyAlias,
            );
        }

        return $consumers;
    }

    /**
     * The element-local wiring rules that judge one consumer against another, or the consumer map against the
     * element's own provider map. They run after {@see decodeConsumers()} has finished the whole map, so a
     * per-consumer combination violation anywhere in the element throws before any rule here, whatever order
     * the consumers sit in.
     *
     * The three rules are replicated from {@see WiringPlanner}, so a tree the render would reject never
     * reaches storage; the planner keeps its own copy for writes that bypass the DAL boundary.
     *
     * @param array<string, ContextConsumer> $consumers
     * @param array<string, ContextProvider> $providers
     */
    public function rejectInvalidElementWiring(array $consumers, array $providers): void
    {
        $holders = [];

        foreach ($consumers as $contextKey => $consumer) {
            $propertyKey = $consumer->propertyAlias ?? $contextKey;

            $baseKey = str_contains($propertyKey, '.')
                ? substr($propertyKey, 0, (int) strpos($propertyKey, '.'))
                : $propertyKey;

            if (\array_key_exists($baseKey, $holders)) {
                throw ContentSystemException::propertyAliasCollision($baseKey, $holders[$baseKey], $contextKey);
            }

            $holders[$baseKey] = $contextKey;
        }

        foreach ($consumers as $contextKey => $consumer) {
            if (!$consumer->redistribute) {
                continue;
            }

            if (str_contains($contextKey, '.')) {
                throw ContentSystemException::redistributeWithDottedPath($contextKey);
            }

            if (\array_key_exists($consumer->propertyAlias ?? $contextKey, $providers)) {
                throw ContentSystemException::redistributeConflict($contextKey);
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function distributionConfig(DistributionStrategy $strategy, array $config): DistributionConfig
    {
        return match ($strategy) {
            DistributionStrategy::Broadcast => BroadcastDistributionConfig::fromArray($config),
            DistributionStrategy::Indexed => IndexedDistributionConfig::fromArray($config),
            DistributionStrategy::Iterator => IteratorDistributionConfig::fromArray($config),
            DistributionStrategy::Keyed => KeyedDistributionConfig::fromArray($config),
            DistributionStrategy::Sliced => SlicedDistributionConfig::fromArray($config),
        };
    }

    /**
     * A key set decode closes: a key outside it is a decode failure, so a field the shape does not carry is
     * never silently dropped on the way to the stored model. The write-path descriptor closes the same set,
     * so neither side admits what the other refuses.
     *
     * @param array<array-key, mixed> $data
     * @param list<string> $known
     */
    private function rejectUnknownKeys(array $data, array $known, string $path, string $subject): void
    {
        foreach (array_keys($data) as $key) {
            if (\in_array($key, $known, true)) {
                continue;
            }

            throw ContentSystemException::invalidFieldValueType(
                $path,
                \sprintf('only known %s keys', $subject),
                \sprintf('unknown key "%s"', $key)
            );
        }
    }

    /**
     * A JSON object with a numeric member name decodes to an integer array key, which the distribution
     * factories above cannot take. Reject it here rather than stringifying it back.
     *
     * @param array<array-key, mixed> $map
     *
     * @return array<string, mixed>
     */
    private function stringKeyed(array $map, string $path): array
    {
        $stringKeyed = [];
        foreach ($map as $key => $value) {
            if (!\is_string($key)) {
                throw ContentSystemException::invalidMapKey($path, get_debug_type($key));
            }

            $stringKeyed[$key] = $value;
        }

        return $stringKeyed;
    }
}
