<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\ORM\Write\EntityExistence;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\Struct\Uuid;
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

    /**
     * {@inheritdoc}
     */
    protected function invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        if ($this->definition === $this->versionReference) {
            //parent inheritance with versioning
            $value = $data->getValue() ?? Defaults::LIVE_VERSION;
        } elseif ($this->writeContext->has($this->versionReference, 'versionId')) {
            $value = $this->writeContext->get($this->versionReference, 'versionId');
        } else {
            $value = Defaults::LIVE_VERSION;
        }

        yield $this->storageName => Uuid::fromStringToBytes($value);
    }
}
