<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Version\VersionDefinition;

class VersionField extends FkField
{
    public function __construct()
    {
        parent::__construct('version_id', 'versionId', VersionDefinition::class);

        $this->setFlags(new PrimaryKey(), new Required());
    }

    /**
     * {@inheritdoc}
     */
    protected function invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        $value = $this->writeContext->getContext()->getVersionId();

        //write version id of current object to write context
        $this->writeContext->set($this->definition, 'versionId', $value);

        yield $this->storageName => Uuid::fromStringToBytes($value);
    }
}
