<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\Version\VersionDefinition;

class ReferenceVersionField extends FkField
{
    /**
     * @var EntityDefinition|string
     */
    protected $versionReference;

    public function __construct(string $definition, ?string $storageName = null)
    {
        /** @var string|EntityDefinition $definition */
        $entity = $definition::getEntityName();
        $storageName = $storageName ?? $entity . '_version_id';

        $propertyName = explode('_', $storageName);
        $propertyName = array_map('ucfirst', $propertyName);
        $propertyName = lcfirst(implode($propertyName));

        parent::__construct($storageName, $propertyName, VersionDefinition::class);

        $this->setFlags(new Required());
        $this->versionReference = $definition;
    }

    public function getVersionReference(): string
    {
        return $this->versionReference;
    }
}
