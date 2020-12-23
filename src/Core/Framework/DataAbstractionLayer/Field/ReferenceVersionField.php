<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ReferenceVersionFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;

class ReferenceVersionField extends FkField
{
    /**
     * @var string
     */
    protected $versionReferenceClass;

    /**
     * @var EntityDefinition
     */
    protected $versionReferenceDefinition;

    public function __construct(string $definition, ?string $storageName = null)
    {
        parent::__construct('', '', VersionDefinition::class);

        $this->versionReferenceClass = $definition;
        $this->storageName = $storageName;
    }

    public function compile(DefinitionInstanceRegistry $registry): void
    {
        if ($this->versionReferenceDefinition !== null) {
            return;
        }

        parent::compile($registry);

        $this->versionReferenceDefinition = $registry->get($this->versionReferenceClass);
        $entity = $this->versionReferenceDefinition->getEntityName();
        $storageName = $this->storageName ?? $entity . '_version_id';

        $propertyName = explode('_', $storageName);
        $propertyName = array_map('ucfirst', $propertyName);
        $propertyName = lcfirst(implode('', $propertyName));

        $this->storageName = $storageName;
        $this->propertyName = $propertyName;
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    public function getVersionReferenceDefinition(): EntityDefinition
    {
        return $this->versionReferenceDefinition;
    }

    public function getVersionReferenceClass(): string
    {
        return $this->versionReferenceClass;
    }

    protected function getSerializerClass(): string
    {
        return ReferenceVersionFieldSerializer::class;
    }
}
