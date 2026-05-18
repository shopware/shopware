<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Service;

use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * Adds the associations required by the selected profile record paths.
 *
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ExportCriteriaEnricher
{
    public function __construct(private readonly DefinitionInstanceRegistry $definitionInstanceRegistry)
    {
    }

    public function enrich(ImportExportV2ProfileEntity $profile, Criteria $criteria): void
    {
        $definition = $this->definitionInstanceRegistry->getByEntityName($profile->getEntity());

        foreach ($profile->getRecordPaths() as $path) {
            foreach ($this->resolveAssociationPaths($definition, $path) as $associationPath) {
                // Only load the association chain that the selected export paths
                // actually need, including nested paths such as manufacturer.media.id.
                $criteria->addAssociation($associationPath);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function resolveAssociationPaths(EntityDefinition $definition, string $path): array
    {
        $segments = explode('.', $path);
        $currentDefinition = $definition;
        $associationPath = [];
        $associationPaths = [];

        foreach ($segments as $index => $segment) {
            if ($segment === '*' || ctype_digit($segment)) {
                continue;
            }

            $field = $currentDefinition->getField($segment);
            if (!$field instanceof AssociationField) {
                break;
            }

            $associationPath[] = $segment;
            $associationPaths[] = implode('.', $associationPath);

            $nextDefinition = $this->resolveAssociationDefinition($field);
            if ($nextDefinition === null || $index === \count($segments) - 1) {
                break;
            }

            $currentDefinition = $nextDefinition;
        }

        return $associationPaths;
    }

    private function resolveAssociationDefinition(AssociationField $field): ?EntityDefinition
    {
        if ($field instanceof ManyToOneAssociationField || $field instanceof OneToManyAssociationField) {
            return $field->getReferenceDefinition();
        }

        if ($field instanceof ManyToManyAssociationField) {
            return $field->getToManyReferenceDefinition();
        }

        return null;
    }
}
