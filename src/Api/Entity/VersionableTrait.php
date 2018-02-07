<?php

namespace Shopware\Api\Entity;

use Shopware\Api\Version\Struct\VersionBasicStruct;

trait VersionableTrait
{
    /**
     * @var VersionBasicStruct
     */
    protected $version;

    public function setVersion(VersionBasicStruct $version): void
    {
        $this->version = $version;
    }

    public function getVersion(): VersionBasicStruct
    {
        return $this->version;
    }
}