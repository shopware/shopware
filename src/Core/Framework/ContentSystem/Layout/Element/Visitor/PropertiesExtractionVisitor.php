<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Visitor;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Util\Hasher;

/**
 * Extracts properties from ContentElements with deduplication.
 * Objects: Deduplicated by entity ID + config hash. Primitives/arrays: Not deduplicated.
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class PropertiesExtractionVisitor implements ElementVisitor
{
    /**
     * @var array<string, mixed> Map of reference ID => data value (not serialized)
     */
    private array $dataStore = [];

    /**
     * @var array<string, array<string, string>> Map of element ID => property key => reference ID
     */
    private array $assignments = [];

    public function __construct(
        private readonly DataLoaderConfigSerializerProvider $configSerializerProvider
    ) {
    }

    public function enter(ContentElement $element): void
    {
        $properties = $element->getProperties();
        $dataRequirements = $element->getDataRequirements();

        // Build map: property key → DataRequirement for config-based deduplication
        $requirementMap = [];
        foreach ($dataRequirements as $requirement) {
            $requirementMap[$requirement->key] = $requirement;
        }

        $this->assignments[$element->getId()] = [];

        foreach ($properties as $key => $value) {
            $requirement = $requirementMap[$key] ?? null;
            $refId = $this->extractAndRegister($value, $requirement, $element->getId(), $key);
            $this->assignments[$element->getId()][$key] = $refId;
        }

        $element->setProperties([]);
    }

    public function leave(ContentElement $element): void
    {
        // Cleanup: Remove empty assignments to keep response clean
        if ($this->assignments[$element->getId()] === []) {
            unset($this->assignments[$element->getId()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->dataStore;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getAssignments(): array
    {
        return $this->assignments;
    }

    private function extractAndRegister(mixed $value, ?DataRequirement $requirement, string $elementId, string $propertyKey): string
    {
        if (\is_object($value) && $requirement instanceof DataRequirement) {
            return $this->extractObjectWithRequirement($value, $requirement);
        }

        if (\is_object($value)) {
            return $this->extractObjectWithoutRequirement($value);
        }

        $hash = Hasher::hash([$elementId, $propertyKey]);

        if (\is_array($value)) {
            $refId = 'array:' . $hash;
            $this->dataStore[$refId] = $value;

            return $refId;
        }

        // Handle primitives and null (not deduplicated)
        $refId = 'scalar:' . $hash;
        $this->dataStore[$refId] = $value;

        return $refId;
    }

    private function extractObjectWithRequirement(object $value, DataRequirement $requirement): string
    {
        $configHash = $this->generateConfigHash($requirement);
        $refId = $this->generateRefId($value, $configHash);

        if (!\array_key_exists($refId, $this->dataStore) || $this->dataStore[$refId] === null) {
            $this->dataStore[$refId] = $value;
        }

        return $refId;
    }

    private function extractObjectWithoutRequirement(object $value): string
    {
        $refId = 'object:' . spl_object_id($value);

        if (!\array_key_exists($refId, $this->dataStore) || $this->dataStore[$refId] === null) {
            $this->dataStore[$refId] = $value;
        }

        return $refId;
    }

    private function generateConfigHash(DataRequirement $requirement): string
    {
        $configArray = $this->configSerializerProvider->encode(
            $requirement->source,
            $requirement->config
        );

        $this->canonicalizeConfig($configArray);

        return Hasher::hash($configArray);
    }

    /**
     * @param array<int|string, mixed> $config
     */
    private function canonicalizeConfig(array &$config): void
    {
        ksort($config);

        foreach ($config as &$value) {
            if (\is_array($value)) {
                // Sort numeric arrays (like associations list) by value
                if (array_is_list($value)) {
                    sort($value);
                } else {
                    $this->canonicalizeConfig($value);
                }
            }
        }
    }

    private function generateRefId(object $value, string $configHash): string
    {
        if ($value instanceof Entity) {
            return \sprintf(
                '%s:%s:%s',
                $value->getApiAlias(),
                $value->getUniqueIdentifier(),
                $configHash
            );
        }

        if ($value instanceof Struct) {
            return \sprintf(
                '%s:%s:%s',
                $value->getApiAlias(),
                spl_object_id($value),
                $configHash
            );
        }

        return \sprintf(
            '%s:%s:%s',
            'object',
            spl_object_id($value),
            $configHash
        );
    }
}
