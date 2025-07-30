<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class FulltextSearchRegistry
{
    private const CONFIG_KEY = 'fulltext_search.indexed_fields';

    /**
     * @var array<string, true>|null
     */
    private ?array $indexedFields = null;

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractKeyValueStorage $keyValueStorage
    ) {
    }

    public function isFieldFulltextEnabled(EntityDefinition $definition, string $fieldName, string $root): bool
    {
        $this->loadIndexedFields();

        $real = EntityDefinitionQueryHelper::getField($fieldName, $definition, $root);
        $definition = EntityDefinitionQueryHelper::getAssociatedDefinition(
            $definition,
            $fieldName
        );

        $entityName = $definition->getEntityName();

        if ($real instanceof TranslatedField) {
            $entityName = $definition->getTranslationDefinition()->getEntityName();
        }

        $key = sprintf('%s.%s', $entityName, $real->getPropertyName());

        return isset($this->indexedFields[$key]);
    }

    /**
     * @return array<string>
     */
    public function getAllFulltextEnabledFields(): array
    {
        $this->loadIndexedFields();

        return array_keys($this->indexedFields ?? []);
    }

    /**
     * @param array<string> $fieldKeys
     */
    public function addIndexedFields(array $fieldKeys): void
    {
        $this->loadIndexedFields();

        foreach ($fieldKeys as $fieldKey) {
            $this->indexedFields[$fieldKey] = true;
        }

        $this->saveIndexedFields();
    }

    public function reset(): void
    {
        $this->indexedFields = null;
    }

    private function loadIndexedFields(): void
    {
        if ($this->indexedFields !== null) {
            return;
        }

        $data = $this->keyValueStorage->get(self::CONFIG_KEY, '{}');
        $decoded = json_decode($data, true);

        $this->indexedFields = is_array($decoded) ? $decoded : [];
    }

    private function saveIndexedFields(): void
    {
        $data = json_encode($this->indexedFields ?? []);
        $this->keyValueStorage->set(self::CONFIG_KEY, $data);
    }
} 