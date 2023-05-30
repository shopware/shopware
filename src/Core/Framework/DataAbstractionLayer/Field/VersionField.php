<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\VersionFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class VersionField extends FkField
{
    public function __construct()
    {
        parent::__construct('version_id', 'versionId', VersionDefinition::class);

        $this->addFlags(new PrimaryKey(), new Required());
    }

    protected function getSerializerClass(): string
    {
        return VersionFieldSerializer::class;
    }
}
