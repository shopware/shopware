<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\VersionTransformation;

interface ApiVersionTransformation
{
    public function getVersion(): int;

    public function getControllerAction(): string;
}
