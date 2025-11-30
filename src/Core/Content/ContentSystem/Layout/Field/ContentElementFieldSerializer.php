<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Field;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config\BroadcastDistributionConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextDefinitions;
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
 * @phpstan-import-type ContextConsumerData from ContextConsumersFieldSerializer
 * @phpstan-import-type DataRequirementData from DataRequirementsFieldSerializer
 *
 * @phpstan-type ContentElementData array{
 *   id: string,
 *   component: string,
 *   properties: array<string, mixed>,
 *   data_requirements?: array<string, DataRequirementData>,
 *   slots?: array<string, list<array<string, mixed>>>,
 *   provides_context?: array<string, array<string, mixed>>,
 *   accepts_context?: array<string, ContextConsumerData>
 * }
 *
 * @internal
 */
#[Package('discovery')]
class ContentElementFieldSerializer extends AbstractFieldSerializer
{
    public function __construct(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry,
        private readonly DataRequirementsFieldSerializer $dataRequirementsSerializer,
        private readonly ContextProvidersFieldSerializer $contextProvidersSerializer,
        private readonly ContextConsumersFieldSerializer $contextConsumersSerializer,
        private readonly ElementSlotsFieldSerializer $elementSlotsSerializer
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

        if ($value === null) {
            yield $field->getStorageName() => null;

            return;
        }

        if ($value instanceof ContentElement) {
            $value = $this->serializeContentElement($value);
        }

        if (!\is_array($value)) {
            throw ContentSystemException::invalidFieldValueType('element', 'array|ContentElement', \gettype($value));
        }

        yield $field->getStorageName() => Json::encode($value);
    }

    public function decode(Field $field, mixed $value): ?ContentElement
    {
        if (!$field instanceof ContentElementField) {
            throw ContentSystemException::invalidFieldType(ContentElementField::class, $field::class);
        }

        if ($value === null) {
            return null;
        }

        if (\is_string($value)) {
            $value = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        }

        if (!\is_array($value)) {
            throw ContentSystemException::invalidFieldValueType('element', 'array', \gettype($value));
        }

        return $this->decodeElement($value);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function decodeElement(array $data): ContentElement
    {
        if (!\array_key_exists('id', $data) || !\is_string($data['id'])) {
            throw ContentSystemException::invalidFieldValueType('id', 'string', \gettype($data['id'] ?? null));
        }

        if (!\array_key_exists('component', $data) || !\is_string($data['component'])) {
            throw ContentSystemException::invalidFieldValueType('component', 'string', \gettype($data['component'] ?? null));
        }

        $dataRequirementsField = new DataRequirementsField('data_requirements', 'dataRequirements');
        $dataRequirements = \array_key_exists('data_requirements', $data) && \is_array($data['data_requirements'])
            ? $this->dataRequirementsSerializer->decode($dataRequirementsField, $data['data_requirements'])
            : [];

        $contextProvidersField = new ContextProvidersField('provides_context', 'providesContext');
        $contextConsumersField = new ContextConsumersField('accepts_context', 'acceptsContext');

        // Null default matches decode() return type; ?? [] used below for type safety
        $providers = \array_key_exists('provides_context', $data) && \is_array($data['provides_context'])
            ? $this->contextProvidersSerializer->decode($contextProvidersField, $data['provides_context'])
            : null;

        $consumers = \array_key_exists('accepts_context', $data) && \is_array($data['accepts_context'])
            ? $this->contextConsumersSerializer->decode($contextConsumersField, $data['accepts_context'])
            : null;

        $providers = $this->expandRedistributeFlags($providers ?? [], $consumers ?? []);

        $contextDefinitions = new ContextDefinitions($providers, $consumers ?? []);

        // Lazy-loaded to break circular dependency
        $slotsField = new ElementSlotsField('slots', 'slots');
        $slots = \array_key_exists('slots', $data) && \is_array($data['slots'])
            ? ($this->elementSlotsSerializer->decode($slotsField, $data['slots']) ?? [])
            : [];

        return new ContentElement(
            $data['id'],
            $data['component'],
            $dataRequirements ?? [],
            $data['properties'] ?? [],
            $slots,
            $contextDefinitions
        );
    }

    /**
     * @return ContentElementData
     */
    public function serializeContentElement(ContentElement $element): array
    {
        $array = [
            'id' => $element->getId(),
            'component' => $element->getComponent(),
            'properties' => $this->serializeProperties($element->getProperties()),
        ];

        $dataRequirements = $element->getDataRequirements();
        if ($dataRequirements !== []) {
            $serializedRequirements = array_map(function ($requirement) {
                return $this->dataRequirementsSerializer->serializeDataRequirement($requirement);
            }, $dataRequirements);
            $array['data_requirements'] = $serializedRequirements;
        }

        if ($element->hasSlots()) {
            $array['slots'] = $this->elementSlotsSerializer->serializeSlots($element->getSlots());
        }

        $providers = $element->getProvidesContext();
        $consumers = $element->getAcceptsContext();

        if ($providers !== []) {
            $serializedProviders = [];
            foreach ($providers as $key => $provider) {
                // Skip virtual providers - they'll be regenerated on deserialization
                if ($this->isVirtualProvider($key, $consumers)) {
                    continue;
                }

                $serializedProviders[$key] = $this->contextProvidersSerializer->serializeContextProvider($provider);
            }

            if ($serializedProviders !== []) {
                $array['provides_context'] = $serializedProviders;
            }
        }

        if ($consumers !== []) {
            $serializedConsumers = array_map(function ($consumer) {
                return $this->contextConsumersSerializer->serializeContextConsumer($consumer);
            }, $consumers);
            $array['accepts_context'] = $serializedConsumers;
        }

        return $array;
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
     * @param array<string, mixed> $properties
     *
     * @return array<string, mixed>
     */
    private function serializeProperties(array $properties): array
    {
        $serialized = [];

        foreach ($properties as $key => $value) {
            if (\is_object($value) && method_exists($value, 'toArray')) {
                $serialized[$key] = $value->toArray();
                continue;
            }

            $serialized[$key] = $value;
        }

        return $serialized;
    }

    /**
     * Checks if provider is virtual (auto-generated from redistribute flag).
     *
     * @param array<string, ContextConsumer> $consumers
     */
    private function isVirtualProvider(string $providerKey, array $consumers): bool
    {
        foreach ($consumers as $consumerKey => $consumer) {
            $generatedKey = $consumer->getGeneratedProviderKey($consumerKey);

            if ($generatedKey === $providerKey) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generates virtual broadcast providers from consumer redistribute flags (parse-time only)
     *
     * @param array<string, ContextProvider> $providers
     * @param array<string, ContextConsumer> $consumers
     *
     * @return array<string, ContextProvider>
     */
    private function expandRedistributeFlags(array $providers, array $consumers): array
    {
        $propertyKeys = [];

        foreach ($consumers as $contextKey => $consumer) {
            $propertyKey = $consumer->propertyAlias ?? $contextKey;

            $baseKey = str_contains($propertyKey, '.')
                ? substr($propertyKey, 0, (int) strpos($propertyKey, '.'))
                : $propertyKey;

            if (\array_key_exists($baseKey, $propertyKeys)) {
                throw ContentSystemException::propertyAliasCollision(
                    $baseKey,
                    $propertyKeys[$baseKey],
                    $contextKey
                );
            }

            $propertyKeys[$baseKey] = $contextKey;
        }

        foreach ($consumers as $contextKey => $consumer) {
            if (!$consumer->redistribute) {
                continue;
            }

            if (str_contains($contextKey, '.')) {
                throw ContentSystemException::redistributeWithDottedPath($contextKey);
            }

            $providerKey = $consumer->consumerAlias ?? $contextKey;

            if (\array_key_exists($providerKey, $providers)) {
                throw ContentSystemException::redistributeConflict($contextKey);
            }

            $providers[$providerKey] = new ContextProvider(
                $consumer->type,
                new BroadcastDistributionConfig(null)
            );
        }

        return $providers;
    }
}
