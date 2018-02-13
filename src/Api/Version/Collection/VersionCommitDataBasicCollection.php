<?php declare(strict_types=1);

namespace Shopware\Api\Version\Collection;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Version\Struct\VersionCommitDataBasicStruct;
use Shopware\Api\Entity\EntityCollection;

class VersionCommitDataBasicCollection extends EntityCollection
{
    /**
     * @var VersionCommitDataBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? VersionCommitDataBasicStruct
    {
        return parent::get($id);
    }

    public function current(): VersionCommitDataBasicStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return VersionCommitDataBasicStruct::class;
    }

    public function filterByEntity(string $definition): VersionCommitDataBasicCollection
    {
        return $this->filter(function(VersionCommitDataBasicStruct $change) use ($definition) {
            /** @var string|EntityDefinition $definition */
            return $change->getEntityName() === $definition::getEntityName();
        });
    }

    public function filterByEntityPrimary(string $definition, array $primary): VersionCommitDataBasicCollection
    {
        return $this->filter(function(VersionCommitDataBasicStruct $change) use ($definition, $primary) {
            /** @var string|EntityDefinition $definition */
            if ($change->getEntityName() !== $definition::getEntityName()) {
                return false;
            }
            $diff = array_intersect($primary, $change->getEntityId());

            return $diff === $primary;
        });
    }
}
