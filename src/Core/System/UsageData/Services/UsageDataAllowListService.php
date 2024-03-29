<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Services;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\UsageDataException;

/**
 * @internal
 */
#[Package('data-services')]
class UsageDataAllowListService
{
    /**
     * @var array<string, string[]>
     */
    private array $allowList;

    public function __construct()
    {
        $this->allowList = self::getDefaultUsageDataAllowList();
    }

    /**
     * @return array<string, string[]>
     */
    public static function getDefaultUsageDataAllowList(): array
    {
        $file = file_get_contents(__DIR__ . '/../usage-data-allow-list.json');

        if (!$file) {
            throw UsageDataException::failedToLoadDefaultAllowList();
        }

        return json_decode($file, true, flags: \JSON_THROW_ON_ERROR);
    }

    public function getFieldsToSelectFromDefinition(EntityDefinition $definition): FieldCollection
    {
        $fieldsToSelect = new FieldCollection();

        $entityName = $definition->getEntityName();

        if (!$this->isEntityAllowed($entityName)) {
            return $fieldsToSelect;
        }

        foreach ($this->allowList[$entityName] as $propertyName) {
            $field = $definition->getField($propertyName);

            if ($field === null) {
                continue;
            }

            $fieldsToSelect->add($field);
        }

        return $fieldsToSelect;
    }

    public function isEntityAllowed(string $entityName): bool
    {
        return \array_key_exists($entityName, $this->allowList);
    }
}
