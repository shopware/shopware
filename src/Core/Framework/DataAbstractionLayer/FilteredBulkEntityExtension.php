<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;

/**
 * EntityDefinitions allow only regular EntityExtension, this class maps BulkyEntityExtensions to EntityExtensions
 *
 * @internal
 */
#[Package('framework')]
class FilteredBulkEntityExtension extends EntityExtension
{
    public function __construct(private readonly string $entityName, private readonly BulkEntityExtension $bulkExtension)
    {
    }

    public function extendFields(FieldCollection $collection): void
    {
        try {
            foreach ($this->bulkExtension->collect() as $entity => $fields) {
                if ($entity !== $this->entityName) {
                    continue;
                }

                foreach ($fields as $field) {
                    $collection->add($field);
                }
            }
        } catch (\Throwable $e) {
            if (str_starts_with($e->getMessage(), 'Class "') && str_ends_with($e->getMessage(), '" not found')) {
                // Ignore autoload issues, as they might happen during plugin lifecycle
                return;
            }

            throw $e;
        }

    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }
}
