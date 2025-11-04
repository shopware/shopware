<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Field;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config\BroadcastDistributionConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Content\ContentSystem\Layout\Element\Slot\ElementSlots;
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

        if ($value instanceof ContentElement) {
            $value = $this->serializeContentElement($value);
        }

        if ($value !== null) {
            $value = Json::encode($value);
        }

        yield $field->getStorageName() => $value;
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
            throw ContentSystemException::invalidFieldValueType('layout', 'array', \gettype($value));
        }

        return $this->decodeElement($value);
    }

    /**
     * Deserializes ContentElement from array format (supports recursive element trees).
     *
     * @param array<string, mixed> $data
     */
    public function decodeElement(array $data): ContentElement
    {
        if (!isset($data['id']) || !\is_string($data['id'])) {
            throw ContentSystemException::invalidFieldValueType('id', 'string', \gettype($data['id'] ?? null));
        }

        if (!isset($data['type']) || !\is_string($data['type'])) {
            throw ContentSystemException::invalidFieldValueType('type', 'string', \gettype($data['type'] ?? null));
        }

        $dataRequirementsField = new DataRequirementsField('data_requirements', 'dataRequirements');
        $dataRequirements = isset($data['data_requirements']) && \is_array($data['data_requirements'])
            ? $this->dataRequirementsSerializer->decode($dataRequirementsField, $data['data_requirements'])
            : [];

        $contextProvidersField = new ContextProvidersField('provides_context', 'providesContext');
        $contextConsumersField = new ContextConsumersField('accepts_context', 'acceptsContext');

        // Null default matches decode() return type; ?? [] used below for type safety
        $providers = isset($data['provides_context']) && \is_array($data['provides_context'])
            ? $this->contextProvidersSerializer->decode($contextProvidersField, $data['provides_context'])
            : null;

        $consumers = isset($data['accepts_context']) && \is_array($data['accepts_context'])
            ? $this->contextConsumersSerializer->decode($contextConsumersField, $data['accepts_context'])
            : null;

        $providers = $this->expandRedistributeFlags($providers ?? [], $consumers ?? []);

        $contextDefinitions = new ContextDefinitions($providers, $consumers ?? []);

        // Lazy-loaded to break circular dependency
        $slotsField = new ElementSlotsField('slots', 'slots');
        $slots = isset($data['slots']) && \is_array($data['slots'])
            ? ($this->elementSlotsSerializer->decode($slotsField, $data['slots']) ?? ElementSlots::empty())
            : ElementSlots::empty();

        return new ContentElement(
            id: $data['id'],
            type: $data['type'],
            dataRequirements: $dataRequirements ?? [],
            properties: $data['properties'] ?? [],
            slots: $slots,
            contextDefinitions: $contextDefinitions
        );
    }

    /**
     * Serializes ContentElement to array format (supports recursive serialization).
     *
     * @return array<string, mixed>
     */
    public function serializeContentElement(ContentElement $element): array
    {
        $array = [
            'id' => $element->getId(),
            'type' => $element->getType(),
            'properties' => $this->serializeProperties($element->getProperties()),
        ];

        $dataRequirements = $element->getDataRequirements();
        if ($dataRequirements !== []) {
            $serializedRequirements = [];
            foreach ($dataRequirements as $key => $requirement) {
                $serializedRequirements[$key] = $this->dataRequirementsSerializer->serializeDataRequirement($requirement);
            }
            $array['data_requirements'] = $serializedRequirements;
        }

        if (!$element->getSlots()->isEmpty()) {
            $array['slots'] = $this->elementSlotsSerializer->serializeElementSlots($element->getSlots());
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
            $serializedConsumers = [];
            foreach ($consumers as $key => $consumer) {
                $serializedConsumers[$key] = $this->contextConsumersSerializer->serializeContextConsumer($consumer);
            }
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
     * Serializes properties, handling nested objects with toArray() method.
     *
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
            } else {
                $serialized[$key] = $value;
            }
        }

        return $serialized;
    }

    /**
     * Checks if provider is virtual (auto-generated from redistribute flag, not persisted)
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
        foreach ($consumers as $contextKey => $consumer) {
            if (!$consumer->redistribute) {
                continue;
            }

            // Validate: no dotted paths with redistribute
            if (str_contains($contextKey, '.')) {
                throw ContentSystemException::redistributeWithDottedPath($contextKey);
            }

            $providerKey = $consumer->consumerAlias ?? $contextKey;

            // Validate: no conflict with explicit provider
            if (isset($providers[$providerKey])) {
                throw ContentSystemException::redistributeConflict($contextKey);
            }

            $providers[$providerKey] = new ContextProvider(
                type: $consumer->type,
                config: new BroadcastDistributionConfig(consumerAlias: null)
            );
        }

        return $providers;
    }
}
