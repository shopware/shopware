<?php

namespace Shopware\Api\Entity;

use Shopware\Api\Version\Struct\VersionBasicStruct;

interface Versionable
{
    public function setVersion(VersionBasicStruct $version): void;
    public function getVersion(): VersionBasicStruct;
}