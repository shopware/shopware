<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Field;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config\BroadcastDistributionConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config\IndexedDistributionConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config\IteratorDistributionConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config\KeyedDistributionConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config\SlicedDistributionConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DistributionStrategy;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextProvider;
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
 * Serializes context providers map to/from JSON.
 *
 * @internal
 */
#[Package('discovery')]
class ContextProvidersFieldSerializer extends AbstractFieldSerializer
{
    public function __construct(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry
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
            if (!\is_array($config)) {
                continue;
            }
            $providers[$key] = $this->deserializeContextProvider($key, $config);
        }

        return $providers;
    }

    /**
     * Public for ContentElementFieldSerializer to serialize nested providers.
     *
     * Note: Uses DistributionConfig::toArray() which must remain for runtime usage.
     *
     * @return array<string, mixed>
     */
    public function serializeContextProvider(ContextProvider $provider): array
    {
        return \array_merge(
            [
                'type' => $provider->type->value,
                'strategy' => $provider->config->getStrategy()->value,
            ],
            $provider->config->toArray()
        );
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
     * Deserializes a context provider from configuration array.
     *
     * @param array<string, mixed> $config
     */
    private function deserializeContextProvider(string $key, array $config): ContextProvider
    {
        $type = ContextType::from($config['type'] ?? 'single');

        if ($type === ContextType::Single) {
            // Single context provider uses broadcast strategy
            return new ContextProvider(
                type: $type,
                config: BroadcastDistributionConfig::fromArray($config)
            );
        }

        // Collection context provider - determine distribution strategy
        $strategyName = $config['distribution'] ?? 'broadcast';
        $strategy = DistributionStrategy::from($strategyName);

        $distributionConfig = match ($strategy) {
            DistributionStrategy::Indexed => IndexedDistributionConfig::fromArray($config),
            DistributionStrategy::Keyed => KeyedDistributionConfig::fromArray($config),
            DistributionStrategy::Sliced => SlicedDistributionConfig::fromArray($config),
            DistributionStrategy::Iterator => IteratorDistributionConfig::fromArray($config),
            DistributionStrategy::Broadcast => BroadcastDistributionConfig::fromArray($config),
        };

        return new ContextProvider(
            type: $type,
            config: $distributionConfig
        );
    }
}
