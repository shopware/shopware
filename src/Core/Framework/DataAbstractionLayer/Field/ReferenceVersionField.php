<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ReferenceVersionFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
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

    /**
     * @var string|null
     */
    protected $storageName;

    public function __construct(
        string $definition,
        ?string $storageName = null,
        ?string $propertyName = null
    ) {
        $entity = $definition;
        if (\is_subclass_of($definition, EntityDefinition::class)) {
            $entity = (new $definition())->getEntityName();
        }

        $storageName ??= $entity . '_version_id';

        if ($propertyName === null) {
            $buildPropertyName = explode('_', $storageName);
            $buildPropertyName = array_map('ucfirst', $buildPropertyName);
            $propertyName = lcfirst(implode('', $buildPropertyName));
        }

        parent::__construct($storageName, $propertyName, VersionDefinition::class);

        $this->versionReferenceClass = $definition;
        $this->storageName = $storageName;
    }

    public function getVersionReferenceDefinition(): EntityDefinition
    {
        if ($this->versionReferenceDefinition === null) {
            $this->compileLazy();
        }

        return $this->versionReferenceDefinition;
    }

    public function getVersionReferenceClass(): string
    {
        if ($this->versionReferenceClass === null) {
            $this->compileLazy();
        }

        return $this->versionReferenceClass;
    }

    protected function getSerializerClass(): string
    {
        return ReferenceVersionFieldSerializer::class;
    }

    protected function compileLazy(): void
    {
        parent::compileLazy();

        \assert($this->registry !== null, 'registry could not be null, because the `compile` method must be called first');
        $this->versionReferenceDefinition = $this->registry->getByClassOrEntityName($this->versionReferenceClass);
        $this->versionReferenceClass = $this->versionReferenceDefinition->getClass();
    }
}
