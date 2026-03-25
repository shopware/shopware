<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Field;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\DistributionConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\IndexedDistributionConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\IteratorDistributionConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\KeyedDistributionConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\SlicedDistributionConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\AbstractFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * PHPStan currently doesn't support a syntax like
 * array{type: 'single'|'collection'}&BroadcastDistributionConfigData or
 * array{type: 'single'|'collection', ...BroadcastDistributionConfigData}
 *
 * @phpstan-type BroadcastProviderData array{
 *   type: 'single'|'collection',
 *   distribution: 'broadcast',
 *   consumer_alias: string|null
 * }
 * @phpstan-type IndexedProviderData array{
 *   type: 'single'|'collection',
 *   distribution: 'indexed',
 *   consumer_alias: string|null
 * }
 * @phpstan-type IteratorProviderData array{
 *   type: 'single'|'collection',
 *   distribution: 'iterator',
 *   consumer_alias: string|null
 * }
 * @phpstan-type KeyedProviderData array{
 *   type: 'single'|'collection',
 *   distribution: 'keyed',
 *   key_property: string,
 *   consumer_alias: string|null
 * }
 * @phpstan-type SlicedProviderData array{
 *   type: 'single'|'collection',
 *   distribution: 'sliced',
 *   slice_size: int,
 *   consumer_alias: string|null
 * }
 * @phpstan-type ContextProviderData BroadcastProviderData|IndexedProviderData|IteratorProviderData|KeyedProviderData|SlicedProviderData
 *
 * @internal
 */
#[Package('discovery')]
class ContextProvidersFieldSerializer extends AbstractFieldSerializer
{
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

            $providers[$key] = $this->deserializeContextProvider($config);
        }

        return $providers;
    }

    /**
     * @return ContextProviderData
     */
    public function serializeContextProvider(ContextProvider $provider): array
    {
        // The simpler code: return ['type' => $provider->type->value, ...$provider->distributionConfig] was omitted
        // because PHPStan was not able to infer to discriminate the return types
        // (naturally because DistributionConfig::toArray uses a union type)

        $config = $provider->distributionConfig;
        $type = $provider->type->value;

        return match (true) {
            $config instanceof BroadcastDistributionConfig => ['type' => $type, ...$config->toArray()],
            $config instanceof IndexedDistributionConfig => ['type' => $type, ...$config->toArray()],
            $config instanceof IteratorDistributionConfig => ['type' => $type, ...$config->toArray()],
            $config instanceof KeyedDistributionConfig => ['type' => $type, ...$config->toArray()],
            $config instanceof SlicedDistributionConfig => ['type' => $type, ...$config->toArray()],
            default => throw ContentSystemException::invalidFieldType(DistributionConfig::class, $config::class),
        };
    }

    /**
     * @return list<Constraint>
     */
    public function buildConstraints(Field $field): array
    {
        $constraints = [
            new Type('array'),
            new All([
                new Collection(
                    fields: [
                        'type' => [new NotBlank(), new Choice(ContextType::values())],
                        'distribution' => [new NotBlank(), new Choice(DistributionStrategy::values())],
                    ],
                    allowExtraFields: true,
                    allowMissingFields: false
                ),
                new Callback($this->validateDistributionFields(...)),
            ]),
        ];

        if ($field->is(Required::class)) {
            $constraints[] = new NotBlank();
        }

        return $constraints;
    }

    protected function getConstraints(Field $field): array
    {
        return $this->buildConstraints($field);
    }

    /**
     * Validates distribution-specific fields based on distribution type.
     */
    private function validateDistributionFields(mixed $value, ExecutionContextInterface $context): void
    {
        if (!\is_array($value) || !isset($value['distribution'])) {
            return;
        }

        $distribution = $value['distribution'];
        $configClass = match ($distribution) {
            'broadcast' => BroadcastDistributionConfig::class,
            'indexed' => IndexedDistributionConfig::class,
            'iterator' => IteratorDistributionConfig::class,
            'keyed' => KeyedDistributionConfig::class,
            'sliced' => SlicedDistributionConfig::class,
            default => null,
        };

        if ($configClass === null) {
            return;
        }

        foreach ($configClass::buildConstraints() as $fieldName => $fieldConstraints) {
            $fieldValue = $value[$fieldName] ?? null;

            foreach ($fieldConstraints as $constraint) {
                $violations = $context->getValidator()->validate($fieldValue, $constraint);
                foreach ($violations as $violation) {
                    $context->buildViolation((string) $violation->getMessage())
                        ->atPath("[$fieldName]")
                        ->addViolation();
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function deserializeContextProvider(array $config): ContextProvider
    {
        return new ContextProvider(
            ContextType::from($config['type']),
            $this->decodeDistributionConfig($config)
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function decodeDistributionConfig(array $config): DistributionConfig
    {
        return match (DistributionStrategy::from($config['distribution'])) {
            DistributionStrategy::Broadcast => BroadcastDistributionConfig::fromArray($config),
            DistributionStrategy::Indexed => IndexedDistributionConfig::fromArray($config),
            DistributionStrategy::Iterator => IteratorDistributionConfig::fromArray($config),
            DistributionStrategy::Keyed => KeyedDistributionConfig::fromArray($config),
            DistributionStrategy::Sliced => SlicedDistributionConfig::fromArray($config),
        };
    }
}
