<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Version\Aggregate\VersionCommitData;

use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\Version\Aggregate\VersionCommitData\VersionCommitDataStruct;

class VersionCommitDataCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\Framework\Version\Aggregate\VersionCommitData\VersionCommitDataStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? VersionCommitDataStruct
    {
        return parent::get($id);
    }

    public function current(): VersionCommitDataStruct
    {
        return parent::current();
    }

    public function filterByEntity(string $definition): self
    {
        return $this->filter(function (VersionCommitDataStruct $change) use ($definition) {
            /* @var string|EntityDefinition $definition */
            return $change->getEntityName() === $definition::getEntityName();
        });
    }

    public function filterByEntityPrimary(string $definition, array $primary): self
    {
        return $this->filter(function (VersionCommitDataStruct $change) use ($definition, $primary) {
            /** @var string|EntityDefinition $definition */
            if ($change->getEntityName() !== $definition::getEntityName()) {
                return false;
            }
            $diff = array_intersect($primary, $change->getEntityId());

            return $diff === $primary;
        });
    }

    protected function getExpectedClass(): string
    {
        return VersionCommitDataStruct::class;
    }
}
