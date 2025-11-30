<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Field;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config\BroadcastDistributionConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config\DistributionConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config\IndexedDistributionConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config\IteratorDistributionConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config\KeyedDistributionConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config\SlicedDistributionConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DistributionStrategy;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Content\ContentSystem\Layout\Field\HelperSerializer\BroadcastDistributionConfigSerializer;
use Shopware\Core\Content\ContentSystem\Layout\Field\HelperSerializer\IndexedDistributionConfigSerializer;
use Shopware\Core\Content\ContentSystem\Layout\Field\HelperSerializer\IteratorDistributionConfigSerializer;
use Shopware\Core\Content\ContentSystem\Layout\Field\HelperSerializer\KeyedDistributionConfigSerializer;
use Shopware\Core\Content\ContentSystem\Layout\Field\HelperSerializer\SlicedDistributionConfigSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\AbstractFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @phpstan-type BroadcastProviderData array{
 *   type: 'single'|'collection',
 *   strategy: 'broadcast',
 *   distribution: 'broadcast',
 *   consumer_alias: string|null
 * }
 * @phpstan-type IndexedProviderData array{
 *   type: 'single'|'collection',
 *   strategy: 'indexed',
 *   distribution: 'indexed',
 *   consumer_alias: string|null
 * }
 * @phpstan-type KeyedProviderData array{
 *   type: 'single'|'collection',
 *   strategy: 'keyed',
 *   distribution: 'keyed',
 *   key_property: string,
 *   consumer_alias: string|null
 * }
 * @phpstan-type SlicedProviderData array{
 *   type: 'single'|'collection',
 *   strategy: 'sliced',
 *   distribution: 'sliced',
 *   slice_size: int,
 *   consumer_alias: string|null
 * }
 * @phpstan-type IteratorProviderData array{
 *   type: 'single'|'collection',
 *   strategy: 'iterator',
 *   distribution: 'iterator',
 *   consumer_alias: string|null
 * }
 * @phpstan-type ContextProviderData BroadcastProviderData|IndexedProviderData|KeyedProviderData|SlicedProviderData|IteratorProviderData
 *
 * @internal
 */
#[Package('discovery')]
class ContextProvidersFieldSerializer extends AbstractFieldSerializer
{
    public function __construct(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry,
        private readonly BroadcastDistributionConfigSerializer $broadcastSerializer,
        private readonly IndexedDistributionConfigSerializer $indexedSerializer,
        private readonly IteratorDistributionConfigSerializer $iteratorSerializer,
        private readonly KeyedDistributionConfigSerializer $keyedSerializer,
        private readonly SlicedDistributionConfigSerializer $slicedSerializer,
    ) {
        parent::__construct($validator, $definitionRegistry);
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof StorageAware) {
            throw ContentSystemException::invalidFieldType(StorageAware::class, $field::class);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $value = $data->getValue();

        if (\is_array($value)) {
            $encoded = [];
            foreach ($value as $key => $provider) {
                if ($provider instanceof ContextProvider) {
                    $encoded[$key] = $this->serializeContextProvider($provider);
                } else {
                    $encoded[$key] = $provider;
                }
            }
            $value = $encoded;
        }

        if ($value !== null) {
            $value = Json::encode($value);
        }

        yield $field->getStorageName() => $value;
    }

    /**
     * @return array<string, ContextProvider>|null
     */
    public function decode(Field $field, mixed $value): ?array
    {
        if (!$field instanceof ContextProvidersField) {
            throw ContentSystemException::invalidFieldType(ContextProvidersField::class, $field::class);
        }

        if ($value === null) {
            return null;
        }

        if (\is_string($value)) {
            $value = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        }

        if (!\is_array($value)) {
            throw ContentSystemException::invalidFieldValueType('provides_context', 'array', \gettype($value));
        }

        $providers = [];
        foreach ($value as $key => $config) {
            if (!\is_string($key) || !\is_array($config)) {
                continue;
            }
            /** @var ContextProviderData $config */
            $providers[$key] = $this->deserializeContextProvider($config);
        }

        return $providers;
    }

    /**
     * @return ContextProviderData
     */
    public function serializeContextProvider(ContextProvider $provider): array
    {
        $type = $provider->type->value;
        $config = $provider->config;

        return match (true) {
            $config instanceof BroadcastDistributionConfig => [
                'type' => $type,
                'strategy' => 'broadcast',
                ...$this->broadcastSerializer->encode($config),
            ],
            $config instanceof IndexedDistributionConfig => [
                'type' => $type,
                'strategy' => 'indexed',
                ...$this->indexedSerializer->encode($config),
            ],
            $config instanceof KeyedDistributionConfig => [
                'type' => $type,
                'strategy' => 'keyed',
                ...$this->keyedSerializer->encode($config),
            ],
            $config instanceof SlicedDistributionConfig => [
                'type' => $type,
                'strategy' => 'sliced',
                ...$this->slicedSerializer->encode($config),
            ],
            $config instanceof IteratorDistributionConfig => [
                'type' => $type,
                'strategy' => 'iterator',
                ...$this->iteratorSerializer->encode($config),
            ],
            default => throw ContentSystemException::invalidFieldValueType(
                'distribution_config',
                'DistributionConfig subtype',
                $config::class
            ),
        };
    }

    protected function getConstraints(Field $field): array
    {
        $constraints = [
            new Type('array'),
        ];

        if ($field->is(Required::class)) {
            $constraints[] = new NotBlank();
        }

        return $constraints;
    }

    /**
     * @param ContextProviderData $config
     */
    private function deserializeContextProvider(array $config): ContextProvider
    {
        return new ContextProvider(
            ContextType::from($config['type']),
            $this->decodeDistributionConfig($config)
        );
    }

    /**
     * @param ContextProviderData $config
     */
    private function decodeDistributionConfig(array $config): DistributionConfig
    {
        return match (DistributionStrategy::from($config['distribution'])) {
            DistributionStrategy::Broadcast => $this->broadcastSerializer->decode($config),
            DistributionStrategy::Indexed => $this->indexedSerializer->decode($config),
            DistributionStrategy::Iterator => $this->iteratorSerializer->decode($config),
            DistributionStrategy::Keyed => $this->keyedSerializer->decode($config),
            DistributionStrategy::Sliced => $this->slicedSerializer->decode($config),
        };
    }
}
