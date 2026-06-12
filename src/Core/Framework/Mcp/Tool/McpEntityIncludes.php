<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Builds smart default `includes` for entity tool responses.
 *
 * When the caller hasn't specified `includes` in the criteria, this trait introspects
 * the EntityDefinition and the criteria's requested associations to build a focused
 * includes map. All scalar fields are kept, but only explicitly requested associations
 * are included -- stripping auto-loaded noise like thumbnails, extensions, and translated duplicates.
 *
 * The `translated` pseudo-field is always injected for entities with translated fields,
 * ensuring inherited/resolved values (e.g. variant product names) are never lost.
 */
#[Package('framework')]
trait McpEntityIncludes
{
    /**
     * Single entry point for tools. Applies smart default includes when none are
     * provided, and ensures `translated` is present for entities with translated fields.
     */
    private function applyDefaultIncludes(EntityDefinition $definition, Criteria $criteria): void
    {
        if ($criteria->getIncludes() === null) {
            $criteria->setIncludes($this->buildDefaultIncludes($definition, $criteria));

            return;
        }

        $this->ensureTranslatedIncludes($definition, $criteria);
    }

    /**
     * @return array<string, list<string>>
     */
    private function buildDefaultIncludes(EntityDefinition $definition, Criteria $criteria): array
    {
        $includes = [];
        $this->collectIncludes($includes, $definition, $criteria);

        return $includes;
    }

    /**
     * Injects `translated` into user-provided includes for entities with translated fields.
     */
    private function ensureTranslatedIncludes(EntityDefinition $definition, Criteria $criteria): void
    {
        $includes = $criteria->getIncludes();
        \assert($includes !== null, 'ensureTranslatedIncludes is only called when includes are set');

        $this->addTranslatedToIncludes($includes, $definition, $criteria);
        $criteria->setIncludes($includes);
    }

    /**
     * @param array<string, list<string>> $includes
     */
    private function collectIncludes(array &$includes, EntityDefinition $definition, Criteria $criteria): void
    {
        $entityName = $definition->getEntityName();

        if (isset($includes[$entityName])) {
            return;
        }

        $fields = [];
        $hasTranslatedFields = false;

        foreach ($definition->getFields() as $field) {
            if ($field instanceof AssociationField) {
                if ($criteria->hasAssociation($field->getPropertyName())) {
                    $fields[] = $field->getPropertyName();

                    $refDef = $field instanceof ManyToManyAssociationField
                        ? $field->getToManyReferenceDefinition()
                        : $field->getReferenceDefinition();

                    $this->collectIncludes(
                        $includes,
                        $refDef,
                        $criteria->getAssociation($field->getPropertyName()),
                    );
                }

                continue;
            }

            if ($field instanceof TranslatedField) {
                $hasTranslatedFields = true;
            }

            $fields[] = $field->getPropertyName();
        }

        if ($hasTranslatedFields) {
            $fields[] = 'translated';
        }

        $includes[$entityName] = $fields;
    }

    /**
     * @param array<string, list<string>> $includes
     */
    private function addTranslatedToIncludes(array &$includes, EntityDefinition $definition, Criteria $criteria): void
    {
        $entityName = $definition->getEntityName();

        if (!isset($includes[$entityName])) {
            return;
        }

        if ($this->hasTranslatedFields($definition) && !\in_array('translated', $includes[$entityName], true)) {
            $includes[$entityName][] = 'translated';
        }

        foreach ($criteria->getAssociations() as $name => $assocCriteria) {
            $field = $definition->getField($name);
            if (!$field instanceof AssociationField) {
                continue;
            }

            $refDef = $field instanceof ManyToManyAssociationField
                ? $field->getToManyReferenceDefinition()
                : $field->getReferenceDefinition();

            $this->addTranslatedToIncludes($includes, $refDef, $assocCriteria);
        }
    }

    private function hasTranslatedFields(EntityDefinition $definition): bool
    {
        foreach ($definition->getFields() as $field) {
            if ($field instanceof TranslatedField) {
                return true;
            }
        }

        return false;
    }
}
