<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Job\Mapping;

use Shopware\Core\Content\ImportExportV2\ImportExportV2Exception;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class EntityPathHelper
{
    /**
     * @param list<string> $paths
     */
    public function assertPathsExist(EntityDefinition $definition, array $paths, int $recordIndex): void
    {
        foreach ($paths as $path) {
            $this->assertPathExists($definition, $path, $recordIndex);
        }
    }

    /**
     * @param list<string> $paths
     */
    public function addAssociations(EntityDefinition $definition, array $paths, Criteria $criteria): void
    {
        foreach ($paths as $path) {
            if (str_starts_with($path, 'translations.')) {
                $criteria->addAssociation('translations');

                continue;
            }

            $topLevel = explode('.', $path)[0];
            $field = $definition->getField($topLevel);
            if ($field instanceof AssociationField) {
                // The spike only loads associations that are needed by the selected profile paths.
                $criteria->addAssociation($topLevel);
            }
        }
    }

    private function assertPathExists(EntityDefinition $definition, string $path, int $recordIndex): void
    {
        if ($path === '') {
            throw ImportExportV2Exception::invalidImportRecord($recordIndex, 'Empty path is not supported.');
        }

        $segments = explode('.', $path);

        if ($segments[0] === 'translations') {
            if (\count($segments) !== 3) {
                throw ImportExportV2Exception::invalidImportRecord($recordIndex, \sprintf('Path "%s" is not supported.', $path));
            }

            $field = $definition->getField($segments[2]);
            if (!$field instanceof TranslatedField) {
                throw ImportExportV2Exception::invalidImportRecord($recordIndex, \sprintf('Path "%s" is not supported.', $path));
            }

            return;
        }

        $currentDefinition = $definition;

        foreach ($segments as $index => $segment) {
            if ($segment === '*' || ctype_digit($segment)) {
                // Lists can be addressed with either "*" or concrete indexes depending on the format.
                continue;
            }

            $field = $currentDefinition->getField($segment);
            if ($field === null) {
                throw ImportExportV2Exception::invalidImportRecord($recordIndex, \sprintf('Path "%s" is not supported.', $path));
            }

            $isLastSegment = $index === \count($segments) - 1;
            if ($isLastSegment) {
                return;
            }

            if ($field instanceof ManyToOneAssociationField || $field instanceof OneToManyAssociationField) {
                $currentDefinition = $field->getReferenceDefinition();

                continue;
            }

            if ($field instanceof ManyToManyAssociationField) {
                $currentDefinition = $field->getToManyReferenceDefinition();

                continue;
            }

            throw ImportExportV2Exception::invalidImportRecord($recordIndex, \sprintf('Path "%s" is not supported.', $path));
        }
    }
}
